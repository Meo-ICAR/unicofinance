<?php

namespace App\Services\Istruttoria;

use App\Mail\PraticaInIstruttoria;
use App\Models\Pratica;
use App\Services\CambioStatoPratica;
use App\Services\Preventivazione\PracticeFinalizer;
use App\Support\Istruttori;
use Illuminate\Support\Facades\Mail;
use Unico\Core\Documents\PracticeDossier;
use Unico\Core\Models\Client;
use Unico\Core\Models\Document;
use Unico\Core\Models\Employee;
use Unico\Core\Signature\SignatureDemo;

/**
 * Invio della pratica in istruttoria dopo la firma: crea il plico (archivio dei documenti e della modulistica firmata), porta la
 * pratica nello stato «INVIO IN ISTRUTTORIA» e avvisa per email la funzione Back Office (gli istruttori). In modalità dimostrazione
 * l'email parte solo verso l'indirizzo di prova (`ISTRUTTORIA_EMAIL_DEMO`) e, se manca, non parte.
 */
class InvioInIstruttoria
{
    public const STATO = 'INVIO IN ISTRUTTORIA';

    /** Ruolo (employee_types) di chi riceve le pratiche in istruttoria: dipendenti ed esterni che lo hanno in `employee_roles`. */
    public const RUOLO_ISTRUTTORI = 'istruttore';

    public function __construct(private PracticeDossier $plichi, private PracticeFinalizer $finalizer, private CambioStatoPratica $stati, private AssegnaIstruttore $assegnatore, private PresaInCarico $presaInCarico) {}

    /**
     * @return array{plico: Document, destinatari: list<string>, inviata: bool, simulazione: bool}
     */
    public function invia(Pratica $pratica, string $origine = 'agenti'): array
    {
        $plico = $this->plichi->crea($pratica);

        // La banca è scelta dopo la perfezione: se ha uno specialista, la pratica passa a lui.
        $assegnato = $this->assegnatore->assegna($pratica);

        // Se il produttore è anche l'istruttore la pratica va direttamente in «PRESA IN CARICO», senza passare dall'attesa in istruttoria.
        $diretta = $assegnato !== null
            && Istruttori::e($assegnato)
            && $this->assegnatore->dipendenteDelloAgente($pratica)?->is($assegnato) === true;

        if ($diretta) {
            $this->presaInCarico->prendi($pratica, $assegnato, $origine, 'Fascicolo firmato: il produttore è l\'istruttore, presa in carico diretta');
        } else {
            $stato = $this->finalizer->status((string) $pratica->tipoprodotto?->name, self::STATO);
            $this->stati->cambia($pratica, $stato->getKey(), 'Fascicolo firmato: plico inviato in istruttoria', $origine);
        }

        $simulazione = SignatureDemo::attiva();
        $destinatari = $this->destinatari($simulazione);
        $inviata = false;

        if ($destinatari !== [] && ! $diretta) {
            Mail::to($destinatari)->send(new PraticaInIstruttoria($pratica->fresh(['tipoprodotto', 'fornitori']), $plico, $simulazione));
            $inviata = true;
        }

        return ['plico' => $plico, 'destinatari' => $destinatari, 'inviata' => $inviata, 'simulazione' => $simulazione];
    }

    /**
     * Email degli istruttori (chi ha il ruolo «istruttore», dipendenti ed esterni); se nessuno, `ISTRUTTORIA_EMAIL`.
     * In dimostrazione solo l'indirizzo di prova, per non scrivere a persone vere.
     *
     * @return list<string>
     */
    public function destinatari(?bool $simulazione = null): array
    {
        if ($simulazione ?? SignatureDemo::attiva()) {
            return array_values(array_filter([config('istruttoria.email_demo')]));
        }

        $emails = Employee::query()->whereJsonContains('employee_roles', self::RUOLO_ISTRUTTORI)->pluck('email')
            ->merge(Client::query()->whereJsonContains('employee_roles', self::RUOLO_ISTRUTTORI)->pluck('email'))
            ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))->unique()->values()->all();

        return $emails !== [] ? $emails : array_values(array_filter([config('istruttoria.email')]));
    }
}
