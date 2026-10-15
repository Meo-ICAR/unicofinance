<?php

namespace App\Services\Istruttoria;

use App\Mail\PraticaInIstruttoria;
use App\Models\Pratica;
use App\Services\CambioStatoPratica;
use App\Services\Preventivazione\PracticeFinalizer;
use Illuminate\Support\Facades\Mail;
use Unico\Core\Documents\PracticeDossier;
use Unico\Core\Models\BusinessFunction;
use Unico\Core\Models\Document;
use Unico\Core\Signature\SignatureDemo;

/**
 * Invio della pratica in istruttoria dopo la firma: crea il plico (archivio dei documenti e della modulistica firmata), porta la
 * pratica nello stato «INVIO IN ISTRUTTORIA» e avvisa per email la funzione Back Office (gli istruttori). In modalità dimostrazione
 * l'email parte solo verso l'indirizzo di prova (`ISTRUTTORIA_EMAIL_DEMO`) e, se manca, non parte.
 */
class InvioInIstruttoria
{
    public const STATO = 'INVIO IN ISTRUTTORIA';

    public const FUNZIONE_ISTRUTTORI = 'BUS-BO';

    public function __construct(private PracticeDossier $plichi, private PracticeFinalizer $finalizer, private CambioStatoPratica $stati) {}

    /**
     * @return array{plico: Document, destinatari: list<string>, inviata: bool, simulazione: bool}
     */
    public function invia(Pratica $pratica, string $origine = 'agenti'): array
    {
        $plico = $this->plichi->crea($pratica);

        $prodotto = (string) $pratica->tipoprodotto?->name;
        $stato = $this->finalizer->status($prodotto, self::STATO);
        $this->stati->cambia($pratica, $stato->getKey(), 'Fascicolo firmato: plico inviato in istruttoria', $origine);

        $simulazione = SignatureDemo::attiva();
        $destinatari = $this->destinatari($simulazione);
        $inviata = false;

        if ($destinatari !== []) {
            Mail::to($destinatari)->send(new PraticaInIstruttoria($pratica->fresh(['tipoprodotto', 'fornitori']), $plico, $simulazione));
            $inviata = true;
        }

        return ['plico' => $plico, 'destinatari' => $destinatari, 'inviata' => $inviata, 'simulazione' => $simulazione];
    }

    /**
     * Email degli istruttori (membri della funzione Back Office). In dimostrazione solo l'indirizzo di prova, per non scrivere a persone vere.
     *
     * @return list<string>
     */
    public function destinatari(?bool $simulazione = null): array
    {
        if ($simulazione ?? SignatureDemo::attiva()) {
            return array_values(array_filter([config('istruttoria.email_demo')]));
        }

        $emails = BusinessFunction::query()->where('code', self::FUNZIONE_ISTRUTTORI)->first()?->employees()->pluck('email')->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))->unique()->values()->all() ?? [];

        return $emails !== [] ? $emails : array_values(array_filter([config('istruttoria.email')]));
    }
}
