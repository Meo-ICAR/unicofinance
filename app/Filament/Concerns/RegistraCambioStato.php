<?php

namespace App\Filament\Concerns;

use App\Services\CambioStatoPratica;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Pagina di modifica di una pratica: se al salvataggio cambia lo stato si chiede la motivazione e il cambio si scrive nello
 * storico degli stati (`pratica_status_history`). Titolo con cognome o ragione sociale e, in piccolo, il tipo prodotto.
 */
trait RegistraCambioStato
{
    public ?string $motivoCambioStato = null;

    protected ?int $statoPrecedenteId = null;

    /** Origine scritta nello storico: admin, agenti... */
    protected function origineCambioStato(): string
    {
        return 'admin';
    }

    protected function beforeSave(): void
    {
        $this->statoPrecedenteId = $this->getRecord()->pratica_stato_id;

        if ($this->statoCambiato() && blank($this->motivoCambioStato)) {
            $this->mountAction('motivoCambioStato');
            $this->halt();
        }
    }

    private function statoCambiato(): bool
    {
        return (string) ($this->data['pratica_stato_id'] ?? '') !== (string) ($this->getRecord()->pratica_stato_id ?? '');
    }

    /** Da chiamare dopo il salvataggio: scrive lo storico e le date se lo stato è cambiato. */
    protected function registraCambioStato(): void
    {
        $pratica = $this->getRecord();
        $date = app(CambioStatoPratica::class)->registra($pratica, $this->statoPrecedenteId, $pratica->pratica_stato_id, $this->motivoCambioStato, $this->origineCambioStato());

        if ($date !== []) {
            $this->refreshFormData($date);
        }

        $this->motivoCambioStato = null;
        $this->statoPrecedenteId = null;
    }

    public function motivoCambioStatoAction(): Action
    {
        return Action::make('motivoCambioStato')
            ->label('Motivo del cambio di stato')
            ->modalHeading('Motivo del cambio di stato')
            ->modalDescription('Lo stato della pratica sta cambiando: indica il motivo, resta nello storico degli stati.')
            ->modalSubmitActionLabel('Salva')
            ->schema([
                Textarea::make('motivazione')->label('Motivazione')->required()->maxLength(1000)->rows(3),
            ])
            ->action(function (array $data): void {
                $this->motivoCambioStato = $data['motivazione'];
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
