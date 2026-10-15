<?php

namespace App\Filament\Resources\Praticas\Pages;

use App\Filament\Resources\Praticas\PraticaResource;
use App\Models\BlacklistClienteFornitore;
use App\Models\Pratica;
use App\Services\Istruttoria\CambioOfferta;
use App\Services\Istruttoria\RichiedentePratica;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Unico\Core\Models\Cliente;

class EditPratica extends EditRecord
{
    protected static string $resource = PraticaResource::class;

    protected function afterSave(): void
    {
        app(RichiedentePratica::class)->ensureClient($this->getRecord());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('cronologiaStati')
                ->label('Cronologia stati')
                ->icon(Heroicon::OutlinedClock)
                ->color('gray')
                ->slideOver()
                ->modalHeading('Cronologia stati pratica')
                ->modalContent(fn (Pratica $record) => view('filament.pratiche.cronologia-stati', [
                    'storico' => $record->praticaStatusHistory()
                        ->with(['employee', 'user'])
                        ->orderByDesc('changed_at')
                        ->orderByDesc('id')
                        ->get(),
                ]))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Chiudi'),
            Action::make('cambiaOfferta')
                ->label('Cambia offerta')
                ->icon(Heroicon::OutlinedArrowsRightLeft)
                ->modalHeading('Cambia offerta')
                ->modalDescription('La pratica viene clonata, con richiedenti, documenti e provvigioni, e la copia passa alla nuova banca. La pratica attuale resta com\'è.')
                ->modalSubmitActionLabel('Clona sulla nuova banca')
                ->schema(fn (Pratica $record): array => [
                    Select::make('banca_id')
                        ->label('Nuova banca')
                        ->options(fn (): array => Cliente::query()
                            ->whereKeyNot($record->clienti_id)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->searchable()
                        ->required()
                        ->rule(fn () => function (string $attribute, mixed $value, \Closure $fail) use ($record): void {
                            $vietata = $record->fornitori_id !== null
                                && BlacklistClienteFornitore::query()
                                    ->where('cliente_id', $value)
                                    ->where('fornitore_id', $record->fornitori_id)
                                    ->where(fn ($query) => $query->whereNull('data_fine')->orWhereDate('data_fine', '>=', today()))
                                    ->exists();

                            if ($vietata) {
                                $fail('Questa banca non lavora con l\'agente della pratica (blacklist).');
                            }
                        }),
                    TextInput::make('assicurazione')
                        ->label('Nuova assicurazione')
                        ->maxLength(255)
                        ->required()
                        ->default($record->assicurazione)
                        ->visible(CambioOfferta::richiedeAssicurazione($record)),
                ])
                ->action(function (array $data, Pratica $record, CambioOfferta $cambioOfferta) {
                    $nuova = $cambioOfferta->handle($record, Cliente::query()->findOrFail($data['banca_id']), $data['assicurazione'] ?? null);

                    Notification::make()
                        ->title('Offerta clonata: '.$nuova->codice_pratica)
                        ->success()
                        ->send();

                    return redirect(PraticaResource::getUrl('edit', ['record' => $nuova]));
                }),
            DeleteAction::make(),
        ];
    }
}
