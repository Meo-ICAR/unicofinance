<?php

namespace App\Services\Istruttoria;

use App\Models\Pratica;
use App\Support\IstruttoreCorrente;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Unico\Core\Enums\DocumentStatus;
use Unico\Core\Models\Document;
use Unico\Core\Models\DocumentRequirement;
use Unico\Core\Models\DocumentType;

/**
 * Contesto di una pratica o della coda dell'istruttore, già calcolato in PHP.
 * Non legge i file dei documenti, i token OAuth né esegue SQL libero.
 */
class ContestoAssistente
{
    public function __construct(private CompletezzaDocumentale $completezza) {}

    public function pratica(Pratica $pratica): string
    {
        $pratica->loadMissing(['praticaStato', 'tipoprodotto', 'employee', 'fornitori', 'richiedente.employer']);

        $esito = $this->completezza->per($pratica);
        $nomi = DocumentType::query()->whereKey($esito['mancanti']->pluck('document_type_id'))->pluck('name', 'id');
        $mancanti = $esito['mancanti']
            ->map(fn (DocumentRequirement $requisito): string => (string) ($nomi[$requisito->document_type_id] ?? 'documento'))
            ->take(12)
            ->implode(', ');
        $ultimo = $pratica->praticaStatusHistory()->orderByDesc('changed_at')->orderByDesc('id')->first();
        $daVedere = $pratica->praticaStatusHistory()->daVisionare()->count();
        $cliente = trim((string) $pratica->cognome_cliente.' '.(string) $pratica->nome_cliente);
        $dirette = $this->richiesteDirette($pratica);

        $righe = [
            'Pratica '.$pratica->codice_pratica.' (id '.$pratica->getKey().').',
            'Cliente: '.($cliente !== '' ? $cliente : '—').'.',
            'Prodotto: '.($pratica->tipoprodotto?->name ?: $pratica->denominazione_prodotto ?: '—').'.',
            'Banca: '.($pratica->denominazione_banca ?: '—').'.',
            'Stato: '.($pratica->praticaStato?->name ?: '—').'.',
            'Inserita il: '.($pratica->data_inserimento_pratica?->format('d/m/Y') ?: '—').'.',
            'Inviata in banca il: '.($pratica->sended_at?->format('d/m/Y') ?: '—').'.',
            'Approvata il: '.($pratica->approved_at?->format('d/m/Y') ?: '—').'.',
            'Erogata il: '.($pratica->erogated_at?->format('d/m/Y') ?: '—').'.',
            'Istruttore: '.($pratica->employee?->name ?: 'non assegnato').'.',
            'Documenti obbligatori: '.$esito['presenti'].'/'.$esito['totale'].'.',
            $mancanti === '' ? 'Non manca nessun documento obbligatorio.' : 'Mancano: '.$mancanti.'.',
            config('istruttoria.documentazione_bloccante')
                ? 'Con documenti mancanti il cambio stato è bloccato.'
                : 'Con documenti mancanti il cambio stato avvisa e non è bloccato.',
            $ultimo === null
                ? 'Nessun cambio nello storico stati.'
                : 'Ultimo cambio: '.($ultimo->changed_at?->format('d/m/Y H:i') ?: '—').' '.($ultimo->status_from ?: '—').' → '.($ultimo->status_to ?: '—').' ('.$ultimo->source.')'
                    .($ultimo->notes ? '. Nota: '.Str::limit((string) $ultimo->notes, 200, '') : '').'.',
            $daVedere > 0
                ? 'Cambi di stato da visionare: '.$daVedere.'. L\'azione in scheda è «Presa visione cambio stato».'
                : 'Nessun cambio di stato da visionare.',
            $dirette
                ? 'Le richieste dirette al cliente sono abilitate: gli integrativi si chiedono al cliente.'
                : 'Le richieste dirette al cliente non sono abilitate: gli integrativi si chiedono al produttore.',
            $this->benestare($pratica),
        ];

        return $this->pulisci(implode("\n", $righe));
    }

    public function coda(): string
    {
        $istruttore = IstruttoreCorrente::id();
        $daPrendere = $this->daPrendereInCarico();
        $righe = [
            'Da prendere in carico (senza istruttore, inserite negli ultimi 30 giorni, escluse respinte e sospese): '.$daPrendere->count().'.'.$this->esempi($daPrendere),
        ];

        if ($istruttore === null) {
            $righe[] = 'Il tuo utente non è collegato a una scheda dipendente. Non elenco le pratiche degli altri istruttori (in attesa della banca e documenti in scadenza).';

            return implode("\n", $righe);
        }

        $banca = $this->inAttesaBanca($istruttore);
        $scadenze = $this->documentiInScadenza($istruttore);

        $righe[] = 'In attesa della banca, tra le tue pratiche, inviate da più di 7 giorni: '.$banca->count().'.'.$this->esempi($banca);
        $righe[] = 'Documenti in scadenza entro 30 giorni sulle tue pratiche aperte: '.$scadenze->count().'.'.$this->esempiDocumenti($scadenze);

        return implode("\n", $righe);
    }

    public function manuale(): string
    {
        $path = resource_path('manuals/manuale-utente.html');

        if (! is_file($path)) {
            return '';
        }

        $testo = html_entity_decode(strip_tags(File::get($path)));
        $testo = (string) preg_replace("/[ \t]+/", ' ', $testo);
        $testo = (string) preg_replace("/\n{3,}/", "\n\n", $testo);

        return Str::limit(trim($testo), 12000, '…');
    }

    public function pulisci(string $testo): string
    {
        $testo = (string) preg_replace('/\b[A-Z]{6}\d{2}[A-Z]\d{2}[A-Z]\d{3}[A-Z]\b/i', '[omesso]', $testo);

        return (string) preg_replace('/sk-ant-[A-Za-z0-9_\-]+/', '[omesso]', $testo);
    }

    private function richiesteDirette(Pratica $pratica): bool
    {
        $grezzo = $pratica->getAttributes()['allows_direct_requests'] ?? null;

        if ($grezzo === null) {
            return (bool) $pratica->fornitori?->allows_direct_requests;
        }

        return (bool) $pratica->allows_direct_requests;
    }

    private function benestare(Pratica $pratica): string
    {
        $datore = $pratica->richiedente?->employer;
        $indicazioni = $datore !== null && filled($datore->richiesta_benestare);

        if (! $indicazioni) {
            return 'Manca l\'indicazione per inoltrare il benestare del datore di lavoro. L\'app non invia il benestare e non scrive sul portale della banca.';
        }

        return 'Sulla scheda del datore di lavoro c\'è un testo su come chiedere il benestare. L\'app non lo invia: va fatto a mano, dall\'azione già presente in interfaccia se c\'è, altrimenti fuori dall\'app.';
    }

    /** @return Builder<Pratica> */
    private function daPrendereInCarico(): Builder
    {
        return Pratica::query()
            ->whereNull('employee_id')
            ->where('is_notowned', false)
            ->whereNull('rejected_at')
            ->whereDoesntHave('praticaStato', fn (Builder $stato) => $stato->where('is_suspended', true))
            ->where('data_inserimento_pratica', '>=', now()->subDays(30)->toDateString());
    }

    /** @return Builder<Pratica> */
    private function inAttesaBanca(int $istruttore): Builder
    {
        return Pratica::query()
            ->where('employee_id', $istruttore)
            ->whereNotNull('sended_at')
            ->where('is_notowned', false)
            ->whereNull('approved_at')
            ->whereNull('rejected_at')
            ->whereNull('erogated_at')
            ->whereDoesntHave('praticaStato', fn (Builder $stato) => $stato->where('is_suspended', true))
            ->where('sended_at', '<=', now()->subDays(7)->toDateString());
    }

    /** @return Builder<Document> */
    private function documentiInScadenza(int $istruttore): Builder
    {
        return Document::query()
            ->where('documentable_type', 'pratica')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays(30)->endOfDay())
            ->whereNotIn('status', [DocumentStatus::REJECTED->value, DocumentStatus::NA->value, DocumentStatus::REVOKED->value])
            ->whereIn('documentable_id', Pratica::query()
                ->where('employee_id', $istruttore)
                ->whereNull('rejected_at')
                ->whereNull('erogated_at')
                ->select('id'));
    }

    /** @param  Builder<Pratica>  $query */
    private function esempi(Builder $query): string
    {
        $nomi = (clone $query)->limit(8)->get(['codice_pratica', 'cognome_cliente', 'denominazione_banca'])
            ->map(function (Pratica $pratica): string {
                $cliente = trim((string) $pratica->cognome_cliente);

                return trim($pratica->codice_pratica.' '.$cliente.($pratica->denominazione_banca ? ' — '.$pratica->denominazione_banca : ''));
            })
            ->implode('; ');

        return $nomi === '' ? '' : ' Esempi: '.$this->pulisci($nomi).'.';
    }

    /** @param  Builder<Document>  $query */
    private function esempiDocumenti(Builder $query): string
    {
        $documenti = (clone $query)->orderBy('expires_at')->limit(8)->get(['name', 'expires_at', 'documentable_id']);
        $codici = Pratica::query()->whereKey($documenti->pluck('documentable_id')->filter()->all())->pluck('codice_pratica', 'id');
        $elenco = $documenti->map(function (Document $documento) use ($codici): string {
            $codice = $codici[$documento->documentable_id] ?? '';

            return trim($documento->name.' '.($documento->expires_at?->format('d/m/Y') ?: '').($codice !== '' ? ' ('.$codice.')' : ''));
        })->implode('; ');

        return $elenco === '' ? '' : ' Esempi: '.$this->pulisci($elenco).'.';
    }
}
