<?php

namespace App\Filament\Concerns;

use App\Services\CambioStatoPratica;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Pagina di modifica di una pratica: se al salvataggio cambia lo stato si chiede la motivazione e il cambio si scrive nello
 * storico degli stati (`pratica_status_history`). Titolo con cognome o ragione sociale e, in piccolo, il tipo prodotto.
 */
trait RegistraCambioStato
{
    public ?string $motivoCambioStato = null;

    /** Il motivo (facoltativo) è già stato chiesto per questo salvataggio: non si richiede di nuovo. */
    public bool $motivoChiesto = false;

    protected ?int $statoPrecedenteId = null;

    /** Origine scritta nello storico: admin, agenti... */
    protected function origineCambioStato(): string
    {
        return 'admin';
    }

    protected function beforeSave(): void
    {
        $this->statoPrecedenteId = $this->getRecord()->pratica_stato_id;

        if ($this->statoCambiato() && $this->documentazioneFermaIlCambio()) {
            $this->halt();
        }

        if ($this->statoCambiato() && ! $this->motivoChiesto) {
            $this->mountAction('motivoCambioStato');
            $this->halt();
        }
    }

    /** Con la documentazione bloccante attiva, uno stato che la richiede non si raggiunge se mancano documenti obbligatori. */
    private function documentazioneFermaIlCambio(): bool
    {
        $servizio = app(CambioStatoPratica::class);
        $mancanti = $servizio->documentiMancanti($this->getRecord(), $this->data['pratica_stato_id'] ?? null);

        if ($mancanti?->isNotEmpty() && $servizio->documentazioneBloccante()) {
            Notification::make()->title('Documentazione incompleta')->body('Mancano: '.$servizio->messaggioMancanti($mancanti))->danger()->send();

            return true;
        }

        return false;
    }

    private function statoCambiato(): bool
    {
        return (string) ($this->data['pratica_stato_id'] ?? '') !== (string) ($this->getRecord()->pratica_stato_id ?? '');
    }

    /** Da chiamare dopo il salvataggio: scrive lo storico e le date se lo stato è cambiato. */
    protected function registraCambioStato(): void
    {
        $pratica = $this->getRecord();
        $servizio = app(CambioStatoPratica::class);

        if ((string) $this->statoPrecedenteId !== (string) $pratica->pratica_stato_id && ($mancanti = $servizio->documentiMancanti($pratica, $pratica->pratica_stato_id))?->isNotEmpty()) {
            Notification::make()->title('Documentazione incompleta')->body('Mancano: '.$servizio->messaggioMancanti($mancanti))->warning()->send();
        }

        $date = $servizio->registra($pratica, $this->statoPrecedenteId, $pratica->pratica_stato_id, $this->motivoCambioStato, $this->origineCambioStato());

        if ($date !== []) {
            $this->refreshFormData($date);
        }

        $this->motivoCambioStato = null;
        $this->motivoChiesto = false;
        $this->statoPrecedenteId = null;
    }

    public function motivoCambioStatoAction(): Action
    {
        return Action::make('motivoCambioStato')
            ->label('Motivo del cambio di stato')
            ->modalHeading('Motivo del cambio di stato')
            ->modalDescription('Lo stato della pratica sta cambiando: il motivo è facoltativo, se lo scrivi resta nello storico degli stati.')
            ->modalSubmitActionLabel('Salva')
            ->schema([
                Textarea::make('motivazione')->label('Motivazione (facoltativa)')->maxLength(1000)->rows(3),
            ])
            ->action(function (array $data): void {
                $this->motivoCambioStato = filled($data['motivazione'] ?? null) ? trim($data['motivazione']) : null;
                $this->motivoChiesto = true;
                $this->save();
            });
    }

    public function getHeading(): string|Htmlable
    {
        return $this->getRecord()->cognome_cliente ?: (string) $this->getRecord()->codice_pratica;
    }

    public function getSubheading(): string|Htmlable|null
    {
        return $this->getRecord()->tipoprodotto?->name;
    }
}
