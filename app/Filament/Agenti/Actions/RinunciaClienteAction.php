<?php

namespace App\Filament\Agenti\Actions;

use App\Services\CambioStatoPratica;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Unico\Core\Models\Pratica;

/**
 * Il produttore dichiara che il cliente rinuncia: la pratica passa a «RINUNCIA CLIENTE» e nello storico degli stati resta
 * «Rinuncia cliente da produttore», così l'istruttore vede un cambio di stato che non ha fatto lui.
 */
class RinunciaClienteAction
{
    public static function make(): Action
    {
        return Action::make('rinunciaCliente')
            ->label('Rinuncia cliente')
            ->icon(Heroicon::OutlinedHandRaised)
            ->color('danger')
            ->visible(function (Pratica $record): bool {
                $stato = app(CambioStatoPratica::class)->statoRinuncia($record);

                return $stato !== null && (string) $record->pratica_stato_id !== (string) $stato->getKey();
            })
            ->requiresConfirmation()
            ->modalHeading('Rinuncia del cliente')
            ->modalDescription('La pratica passa allo stato «RINUNCIA CLIENTE» e l\'istruttore ne è informato.')
            ->modalSubmitActionLabel('Conferma la rinuncia')
            ->schema([
                Textarea::make('nota')->label('Nota (facoltativa)')->maxLength(500)->rows(2),
            ])
            ->action(function (Pratica $record, array $data, $livewire): void {
                app(CambioStatoPratica::class)->rinunciaCliente($record, $data['nota'] ?? null);

                if (method_exists($livewire, 'refreshFormData')) {
                    $livewire->refreshFormData(['pratica_stato_id']);
                }

                Notification::make()->title('Pratica in rinuncia cliente')->success()->send();
            });
    }
}
