<?php

namespace Tests\Feature;

use App\Mail\PraticaInIstruttoria;
use App\Models\Pratica;
use App\Services\Istruttoria\InvioInIstruttoria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\SeedsCoreData;
use Tests\TestCase;
use Unico\Core\Enums\DocumentStatus;
use Unico\Core\Models\Document;
use Unico\Core\Models\DocumentType;
use Unico\Core\Models\Employee;
use Unico\Core\Models\Fornitore;
use Unico\Core\Models\PraticaStato;
use Unico\Core\Models\TipoProdotto;
use ZipArchive;

class InvioInIstruttoriaTest extends TestCase
{
    use RefreshDatabase;
    use SeedsCoreData;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [null, 'core'];

    private function pratica(): Pratica
    {
        $tipo = TipoProdotto::query()->firstOrCreate(['tipo_prodotto' => 'Cessione'], ['name' => 'Cessione']);
        $pratica = Pratica::query()->create(['codice_pratica' => 'IST-1', 'cognome_cliente' => 'Rossi', 'nome_cliente' => 'Mario', 'tipoprodotto_id' => $tipo->getKey(), 'upload_at' => now()]);

        foreach (['Carta identità' => false, 'Fascicolo firmato' => true] as $nome => $firmato) {
            $documento = Document::query()->create([
                'documentable_type' => 'pratica', 'documentable_id' => (string) $pratica->getKey(),
                'document_type_id' => DocumentType::query()->create(['name' => $nome, 'code' => 'IST-'.uniqid()])->getKey(),
                'name' => $nome, 'status' => DocumentStatus::UPLOADED,
            ]);
            $documento->addMediaFromString("%PDF-1.4\n{$nome}")->usingFileName(str($nome)->slug().'.pdf')->toMediaCollection(Document::COLLECTION);

            if ($firmato) {
                $documento->addMediaFromString("%PDF-1.4\n{$nome} FIRMATO")->usingFileName('fascicolo-firmato.pdf')->toMediaCollection(Document::COLLECTION_SIGNED);
            }
        }

        return $pratica;
    }

    private function istruttore(string $email): void
    {
        Employee::query()->create(['name' => 'Istruttore Prova', 'email' => $email, 'employee_roles' => [InvioInIstruttoria::RUOLO_ISTRUTTORI]]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCoreData('pratica_stati', 'tipoprodotto', 'tipoprodotto_sub');
    }

    public function test_the_dossier_is_created_the_state_moves_to_istruttoria_and_the_instructors_are_notified(): void
    {
        Mail::fake();
        config(['unico-core.signature.demo' => false, 'unico-core.signature.driver' => 'fake']);
        $this->istruttore('istruttore@example.test');
        $pratica = $this->pratica();

        $esito = app(InvioInIstruttoria::class)->invia($pratica);

        $this->assertSame(InvioInIstruttoria::STATO, $pratica->fresh()->praticaStato->codice);
        $this->assertNull($pratica->fresh()->sended_at, 'L\'invio in istruttoria non è l\'invio in banca.');
        $this->assertSame(1, $pratica->fresh()->praticaStatusHistory()->count());

        // Il plico contiene i documenti e, per quello firmato, la versione firmata; più l'indice.
        $zip = new ZipArchive;
        $zip->open($esito['plico']->currentMedia()->getPath());
        $contenuti = collect(range(0, $zip->numFiles - 1))->mapWithKeys(fn (int $i): array => [$zip->getNameIndex($i) => $zip->getFromIndex($i)]);
        $zip->close();

        $this->assertCount(3, $contenuti);
        $this->assertTrue($contenuti->contains(fn (string $testo): bool => str_contains($testo, 'FIRMATO')));
        $this->assertTrue($contenuti->has('indice.txt'));

        Mail::assertSent(PraticaInIstruttoria::class, fn (PraticaInIstruttoria $mail): bool => $mail->hasTo('istruttore@example.test'));
        $this->assertTrue($esito['inviata']);
    }

    public function test_in_demo_mode_the_email_goes_only_to_the_demo_address_or_not_at_all(): void
    {
        Mail::fake();
        config(['unico-core.signature.demo' => true, 'unico-core.signature.driver' => 'fake', 'istruttoria.email_demo' => null]);
        $this->istruttore('vero.istruttore@example.test');
        $pratica = $this->pratica();

        $senzaIndirizzo = app(InvioInIstruttoria::class)->invia($pratica);

        $this->assertFalse($senzaIndirizzo['inviata']);
        Mail::assertNothingSent();

        config(['istruttoria.email_demo' => 'demo@example.test']);
        $conIndirizzo = app(InvioInIstruttoria::class)->invia($pratica);

        $this->assertTrue($conIndirizzo['inviata']);
        Mail::assertSent(PraticaInIstruttoria::class, fn (PraticaInIstruttoria $mail): bool => $mail->hasTo('demo@example.test') && ! $mail->hasTo('vero.istruttore@example.test'));
    }

    public function test_the_plain_incoming_state_is_listed_for_the_product_so_the_instructor_can_move_it_to_the_bank(): void
    {
        $this->assertTrue(PraticaStato::query()->where('tipo_finanziamento', 'Cessione')->where('codice', 'INVIO IN BANCA')->where('is_sended', true)->exists());
        $this->assertFalse(PraticaStato::query()->where('codice', 'INVIO IN ISTRUTTORIA')->where('is_sended', true)->exists());
    }

    public function test_the_assigned_instructor_waits_in_istruttoria_and_the_practice_is_not_yet_taken(): void
    {
        Mail::fake();
        config(['unico-core.signature.demo' => false, 'unico-core.signature.driver' => 'fake']);
        $this->istruttore('istruttore@example.test');
        $pratica = $this->pratica();

        app(InvioInIstruttoria::class)->invia($pratica);

        $pratica->refresh();
        $this->assertNotNull($pratica->employee_id, 'L\'istruttore è assegnato.');
        $this->assertSame(InvioInIstruttoria::STATO, $pratica->praticaStato->codice, 'Ma la pratica aspetta in istruttoria finché lui non la apre.');
        $this->assertNull($pratica->presa_in_carico_at);
        Mail::assertSent(PraticaInIstruttoria::class);
    }

    public function test_a_producer_who_is_the_instructor_goes_straight_to_presa_in_carico(): void
    {
        Mail::fake();
        config(['unico-core.signature.demo' => false, 'unico-core.signature.driver' => 'fake']);
        $this->istruttore('istruttore@example.test');
        $istruttore = Employee::query()->where('email', 'istruttore@example.test')->firstOrFail();
        $produttore = Fornitore::query()->create(['name' => 'Istruttore  Prova', 'piva' => '99900000041', 'is_active' => true]);
        $istruttore->update(['name' => 'PROVA ISTRUTTORE']);
        $pratica = $this->pratica();
        $pratica->update(['fornitori_id' => $produttore->getKey()]);

        app(InvioInIstruttoria::class)->invia($pratica);

        $pratica->refresh();
        $this->assertSame($istruttore->getKey(), $pratica->employee_id);
        $this->assertSame('PRESA IN CARICO', $pratica->praticaStato->codice);
        $this->assertTrue($pratica->presa_in_carico_at->isToday());
        $this->assertSame(1, $pratica->praticaStatusHistory()->count());
        Mail::assertNothingSent();
    }
}
