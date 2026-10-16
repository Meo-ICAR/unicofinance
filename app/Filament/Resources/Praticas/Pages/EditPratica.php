<?php

namespace App\Filament\Resources\Praticas\Pages;

use App\Filament\Concerns\RegistraCambioStato;
use App\Filament\Resources\Praticas\PraticaResource;
use App\Models\BlacklistClienteFornitore;
use App\Models\Pratica;
use App\Services\CambioStatoPratica;
use App\Services\Istruttoria\CambioOfferta;
use App\Services\Istruttoria\RichiedentePratica;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Unico\Core\Models\Cliente;

class EditPratica extends EditRecord
{
    use RegistraCambioStato;

    protected static string $resource = PraticaResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->getHeading();
    }

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->avvisaDeiCambiStatoDaVisionare();
    }

    /** Cambi di stato non fatti dall'istruttore (es. rinuncia del produttore) con la loro motivazione, finché non ne prende visione. */
    private function avvisaDeiCambiStatoDaVisionare(): void
    {
        $cambi = $this->getRecord()->praticaStatusHistory()->daVisionare()->with(['user'])->orderBy('changed_at')->get();

        if ($cambi->isEmpty()) {
            return;
        }

        Notification::make()
            ->title('Cambio di stato non fatto da te')
            ->body($cambi->map(fn ($cambio): string => $cambio->changed_at?->format('d/m/Y H:i').' · '.($cambio->source === 'agenti' ? 'Produttore' : ($cambio->user?->name ?? 'altro utente')).': '.($cambio->status_from ?: '—').' → '.$cambio->status_to.($cambio->notes ? ' — '.$cambio->notes : ''))->implode("\n"))
            ->warning()
            ->persistent()
            ->send();
    }

    protected function afterSave(): void
    {
        app(RichiedentePratica::class)->ensureClient($this->getRecord());
        $this->registraCambioStato();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('presaVisione')
                ->label('Presa visione cambio stato')
                ->icon(Heroicon::OutlinedEye)
                ->color('warning')
                ->badge(fn (Pratica $record): int => $record->praticaStatusHistory()->daVisionare()->count())
                ->visible(fn (Pratica $record): bool => $record->praticaStatusHistory()->daVisionare()->exists())
                ->requiresConfirmation()
                ->modalHeading('Presa visione')
                ->modalDescription('Confermi di aver preso visione dei cambi di stato fatti da altri su questa pratica?')
                ->action(function (Pratica $record): void {
                    app(CambioStatoPratica::class)->presaVisione($record);

                    Notification::make()->title('Presa visione registrata')->success()->send();
                }),
            Action::make('cronologiaStati')
                ->label('Cronologia stati')
                ->icon(Heroicon::OutlinedClock)
                ->color('gray')
                ->slideOver()
                ->modalHeading('Cronologia stati pratica')
                ->modalContent(fn (Pratica $record) => view('filament.pratiche.cronologia-stati', [
                    'storico' => $record->praticaStatusHistory()
                        ->with(['employee', 'user', 'seenBy'])
                        ->orderByDesc('changed_at')
                        ->orderByDesc('id')
                        ->get(),
                    'istruttoreUserId' => $record->employee?->user_id,
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
