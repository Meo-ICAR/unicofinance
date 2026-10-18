<?php

namespace App\Filament\Resources\Praticas\Pages;

use App\Filament\Concerns\RegistraCambioStato;
use App\Filament\Resources\Praticas\PraticaResource;
use App\Models\BlacklistClienteFornitore;
use App\Models\Pratica;
use App\Models\User;
use App\Services\CambioStatoPratica;
use App\Services\Istruttoria\CambioOfferta;
use App\Services\Istruttoria\InvioContoEstintivo;
use App\Services\Istruttoria\RichiedentePratica;
use App\Services\Istruttoria\RichiestaContoEstintivo;
use App\Services\Istruttoria\RispostaAssistente;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Unico\Core\Documents\PracticeSignaturePackage;
use Unico\Core\Models\Cliente;
use Unico\Core\Models\DocumentType;

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
        $this->richiediContoEstintivo();
    }

    /** @return Collection<int, DocumentType> */
    protected static function tipoRichiestaConteggio(): Collection
    {
        return DocumentType::query()->where('name', RichiestaContoEstintivo::TIPO_RICHIESTA)->get();
    }

    /** Stato della firma OTP del modulo di richiesta: senza_modulo, dati_mancanti, pronto, stampato, inviato, firmato, rifiutato; null se la richiesta non c'è. */
    protected static function statoRichiestaConteggio(Pratica $pratica): ?string
    {
        $tipi = static::tipoRichiestaConteggio();

        if ($tipi->isEmpty() || ! $pratica->documents()->whereIn('document_type_id', $tipi->modelKeys())->exists()) {
            return null;
        }

        return collect(app(PracticeSignaturePackage::class)->status($pratica, $tipi))->first()['state'] ?? null;
    }

    private function richiediContoEstintivo(): void
    {
        $avviso = app(RichiestaContoEstintivo::class)->sincronizza($this->getRecord());

        if ($avviso !== null) {
            Notification::make()->title('Conto estintivo')->body($avviso)->warning()->persistent()->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->chiediAssistente(),
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
            Action::make('firmaRichiestaConteggio')
                ->label('Richiesta conteggio estintivo: firma OTP')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->color('warning')
                ->visible(fn (Pratica $record): bool => $record->is_notowned && static::statoRichiestaConteggio($record) !== null && ! in_array(static::statoRichiestaConteggio($record), ['firmato', 'inviato'], true))
                ->requiresConfirmation()
                ->modalHeading('Firma OTP della richiesta')
                ->modalDescription('Il modulo «Richiesta Conteggio Estintivo» viene stampato con i dati della pratica e inviato al cliente per la firma con OTP.')
                ->action(function (Pratica $record): void {
                    foreach (app(PracticeSignaturePackage::class)->send($record, static::tipoRichiestaConteggio(), auth()->user()) as $esito) {
                        Notification::make()->title($esito['name'])->body($esito['message'])->color($esito['ok'] ? 'success' : 'danger')->send();
                    }
                }),
            Action::make('aggiornaFirmaRichiestaConteggio')
                ->label('Aggiorna firma richiesta conteggio')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->visible(fn (Pratica $record): bool => $record->is_notowned && static::statoRichiestaConteggio($record) === 'inviato')
                ->action(function (Pratica $record): void {
                    app(PracticeSignaturePackage::class)->refresh($record, static::tipoRichiestaConteggio());

                    Notification::make()->title('Firma: '.str_replace('_', ' ', (string) static::statoRichiestaConteggio($record)))->success()->send();
                }),
            Action::make('inviaContoEstintivo')
                ->label('Invia conto estintivo in banca')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->color('success')
                ->visible(fn (Pratica $record): bool => $record->is_notowned && app(InvioContoEstintivo::class)->documentoApprovato($record) !== null)
                ->modalHeading('Invia conto estintivo in banca')
                ->modalDescription('Il conto estintivo verificato parte per email, con il documento in allegato.')
                ->modalSubmitActionLabel('Invia')
                ->fillForm(fn (Pratica $record): array => ['email' => $record->clienti?->email])
                ->schema([
                    TextInput::make('email')->label('Email della banca')->email()->required()->maxLength(255),
                ])
                ->action(function (Pratica $record, array $data): void {
                    try {
                        app(InvioContoEstintivo::class)->invia($record, $data['email']);
                    } catch (\RuntimeException $e) {
                        Notification::make()->title('Conto estintivo non inviato')->body($e->getMessage())->warning()->send();

                        return;
                    }

                    Notification::make()->title('Conto estintivo inviato')->body($data['email'])->success()->send();
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
                            ->where('is_active', true)
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

    /** Pannello laterale già sulla pratica aperta. Solo staff, solo lettura. */
    private function chiediAssistente(): Action
    {
        return Action::make('chiediAssistente')
            ->label('Chiedi all\'assistente')
            ->icon(Heroicon::OutlinedChatBubbleLeftRight)
            ->color('gray')
            ->visible(fn (): bool => auth()->user() instanceof User && auth()->user()->isStaff())
            ->slideOver()
            ->modalHeading('Assistente istruttoria')
            ->modalDescription('Domande utili: cosa manca per la banca, perché il cambio stato è bloccato, a chi chiedere gli integrativi. La risposta non cambia lo stato, non invia email e non scrive sul portale della banca.')
            ->modalSubmitActionLabel('Chiedi')
            ->schema([
                Placeholder::make('storico')
                    ->label('Conversazione')
                    ->content(function (Pratica $record): HtmlString|string {
                        $messaggi = app(RispostaAssistente::class)->messaggi($record);

                        if ($messaggi === []) {
                            return 'Nessun messaggio precedente su questa pratica.';
                        }

                        $html = collect($messaggi)->map(function (array $messaggio): string {
                            $chi = $messaggio['ruolo'] === 'user' ? 'Tu' : 'Assistente';

                            return '<p class="mb-2"><strong>'.e($chi).'</strong><br>'.nl2br(e($messaggio['testo'])).'</p>';
                        })->implode('');

                        return new HtmlString($html);
                    }),
                Textarea::make('domanda')
                    ->label('Domanda')
                    ->required()
                    ->rows(3)
                    ->maxLength(2000),
            ])
            ->action(function (array $data, Pratica $record): void {
                try {
                    $risposta = app(RispostaAssistente::class)->chiedi((string) $data['domanda'], $record);
                } catch (\RuntimeException $e) {
                    Notification::make()->title('Assistente')->body($e->getMessage())->warning()->persistent()->send();

                    return;
                }

                Notification::make()->title('Assistente')->body(Str::limit($risposta, 800, ''))->success()->persistent()->send();
            });
    }
}
