<?php

namespace App\Filament\Concerns;

use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Illuminate\Contracts\Support\Htmlable;
use Unico\Core\Models\Pratica;
use Unico\Core\Models\PraticaStato;

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

    /** Da chiamare dopo il salvataggio: scrive lo storico se lo stato è cambiato. */
    protected function registraCambioStato(): void
    {
        /** @var Pratica $pratica */
        $pratica = $this->getRecord();
        $nuovo = $pratica->pratica_stato_id;

        if ((string) $this->statoPrecedenteId !== (string) $nuovo) {
            $pratica->praticaStatusHistory()->create([
                'status_from' => $this->statoPrecedenteId === null ? null : PraticaStato::query()->whereKey($this->statoPrecedenteId)->value('name'),
                'status_to' => $nuovo === null ? '' : (string) PraticaStato::query()->whereKey($nuovo)->value('name'),
                'changed_at' => now(),
                'source' => $this->origineCambioStato(),
                'notes' => $this->motivoCambioStato,
                'user_id' => auth()->id(),
            ]);

            $this->valorizzaDateDelloStato($pratica, $nuovo);
        }

        $this->motivoCambioStato = null;
        $this->statoPrecedenteId = null;
    }

    /** Se il nuovo stato è di rifiuto, invio, approvazione o erogazione, la data della pratica si valorizza con oggi (se è vuota). */
    private function valorizzaDateDelloStato(Pratica $pratica, mixed $statoId): void
    {
        $stato = $statoId === null ? null : PraticaStato::query()->find($statoId);

        if ($stato === null) {
            return;
        }

        $date = [];

        foreach (['is_rejected' => 'rejected_at', 'is_sended' => 'sended_at', 'is_approved' => 'approved_at', 'is_erogated' => 'erogated_at'] as $flag => $campo) {
            if ($stato->{$flag} && blank($pratica->{$campo})) {
                $date[$campo] = today();
            }
        }

        if ($date !== []) {
            $pratica->forceFill($date)->save();
            $this->refreshFormData(array_keys($date));
        }
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
