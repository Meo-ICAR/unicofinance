<?php

namespace App\Filament\Resources\Praticas\Schemas;

use App\Filament\Resources\Clients\ClientResource;
use App\Models\Client;
use App\Models\Pratica;
use App\Services\Istruttoria\RichiedentePratica;
use App\Services\PortaleBanca;
use App\Support\ControlloRichiedente;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Livewire\Component;
use Unico\Core\Clients\OpenApiCompanyService;
use Unico\Core\Models\Cliente;
use Unico\Core\Models\TipoProdotto;

class PraticaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Pratica')
                    ->columnSpanFull()
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make('Dati della richiesta')
                            ->icon(Heroicon::OutlinedUser)
                            ->schema([
                                Section::make('Richiedente')
                                    ->columns(4)
                                    ->schema([
                                        Toggle::make('richiedente_persona_fisica')
                                            ->label('Persona fisica')
                                            ->inline(false)
                                            ->live()
                                            ->dehydrated(false)
                                            ->default(true)
                                            ->afterStateHydrated(fn (Toggle $component, ?Pratica $record) => $component->state(
                                                ! preg_match('/^(IT)?\d{11}$/i', trim((string) $record?->codice_fiscale)),
                                            )),
                                        TextInput::make('cognome_cliente')
                                            ->label(fn (Get $get): string => $get('richiedente_persona_fisica') ? 'Cognome' : 'Ragione sociale')
                                            ->live(onBlur: true)
                                            ->columnSpan(fn (Get $get): int => $get('richiedente_persona_fisica') ? 1 : 2),
                                        TextInput::make('nome_cliente')
                                            ->label('Nome')
                                            ->live(onBlur: true)
                                            ->visible(fn (Get $get): bool => (bool) $get('richiedente_persona_fisica')),
                                        TextInput::make('codice_fiscale')
                                            ->label(fn (Get $get): string => $get('richiedente_persona_fisica') ? 'Codice fiscale' : 'Partita IVA')
                                            ->maxLength(16)
                                            ->live(onBlur: true)
                                            ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                                $error = self::taxCodeError((string) $value, (bool) $get('richiedente_persona_fisica'));

                                                if ($error !== null) {
                                                    $fail($error);
                                                }
                                            })
                                            ->afterStateUpdated(fn (?string $state, Get $get) => self::warnAboutTaxCode($state, $get))
                                            ->suffixActions([
                                                self::schedaClienteAction(),
                                                self::openApiAction(),
                                            ]),
                                    ]),
                                Section::make('Prodotto richiesto')
                                    ->columns(3)
                                    ->schema([
                                        Toggle::make('is_notowned')->label('Impegno di terzi')->inline(false),
                                        Select::make('tipoprodotto_id')
                                            ->label('Tipo prodotto')
                                            ->relationship('tipoprodotto', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->live(),
                                        Select::make('clienti_id')
                                            ->label('Banca')
                                            ->relationship('clienti', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->live()
                                            ->afterStateUpdated(fn (Set $set, ?string $state) => $set('denominazione_banca', $state ? Cliente::query()->whereKey($state)->value('name') : null))
                                            ->suffixAction(
                                                Action::make('portaleBanca')
                                                    ->label('Portale della banca')
                                                    ->tooltip('Apri il portale della banca per inserire la pratica')
                                                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                                                    ->url(fn (Get $get): ?string => PortaleBanca::url($get('clienti_id')), shouldOpenInNewTab: true)
                                                    ->visible(fn (Get $get): bool => PortaleBanca::url($get('clienti_id')) !== null),
                                            ),
                                        Hidden::make('denominazione_banca'),
                                        // Cessione e delega si calcolano sulla rata; gli altri prodotti su importo richiesto e durata.
                                        TextInput::make('rata')->label('Rata')->numeric()->prefix('€')->visible(fn (Get $get): bool => self::sullaRata($get)),
                                        TextInput::make('amount')->label('Importo richiesto')->numeric()->prefix('€')->visible(fn (Get $get): bool => ! self::sullaRata($get)),
                                        TextInput::make('nrate')->label('Durata (mesi)')->numeric()->integer()->visible(fn (Get $get): bool => ! self::sullaRata($get)),
                                        TextInput::make('codice_pratica')->label('Numero pratica'),
                                        DatePicker::make('data_inserimento_pratica')->label('Data richiesta'),
                                    ]),
                            ]),
                        Tab::make('Dati di lavorazione')
                            ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                            ->schema([
                                Section::make('Istruttoria')
                                    ->columns(2)
                                    ->schema([
                                        Select::make('pratica_stato_id')
                                            ->label('Stato pratica')
                                            ->relationship('praticaStato', 'name')
                                            ->searchable()
                                            ->preload(),
                                        Select::make('employee_id')
                                            ->label('Istruttore')
                                            ->relationship('employee', 'name')
                                            ->searchable()
                                            ->preload(),
                                        Select::make('fornitori_id')
                                            ->label('Agente')
                                            ->relationship('fornitori', 'name')
                                            ->searchable()
                                            ->preload(),
                                        TextInput::make('denominazione_prodotto')->label('Prodotto'),
                                        TextInput::make('assicurazione')->label('Assicurazione'),
                                    ]),
                                Section::make('Avanzamento')
                                    ->columns(4)
                                    ->schema([
                                        DatePicker::make('sended_at')->label('Invio a banca'),
                                        DatePicker::make('approved_at')->label('Approvazione'),
                                        DatePicker::make('erogated_at')->label('Erogazione'),
                                        DatePicker::make('rejected_at')->label('Rifiuto'),
                                    ]),
                                Section::make('Offerta')
                                    ->hidden()
                                    ->columns(2)
                                    ->schema([
                                        DatePicker::make('offer_valid_until')->label('Offerta valida fino al'),
                                        Textarea::make('withdrawal_terms')->label('Condizioni di recesso')->columnSpanFull(),
                                    ]),
                            ]),
                        Tab::make('Dati finanziari')
                            ->icon(Heroicon::OutlinedBanknotes)
                            ->schema([
                                Section::make('Importi')
                                    ->columns(3)
                                    ->schema([
                                        TextInput::make('net')->label('Importo netto')->numeric()->prefix('€'),
                                        TextInput::make('erogato')->label('Erogato')->numeric()->prefix('€'),
                                    ]),
                                Section::make('Mediazione e costi')
                                    ->columns(3)
                                    ->schema([
                                        TextInput::make('mediation_fee_percent')->label('Mediazione %')->numeric()->suffix('%'),
                                        TextInput::make('mediation_fee_amount')->label('Mediazione €')->numeric()->prefix('€'),
                                        TextInput::make('istruttoria_costs')->label('Costi istruttoria')->numeric()->prefix('€'),
                                        Textarea::make('mediation_fee_terms')->label('Condizioni di mediazione')->columnSpanFull(),
                                    ]),
                            ]),
                    ]),
                Hidden::make('upload_at')->default(now()),
            ]);
    }

    /** Cessione e delega si calcolano sulla rata. */
    private static function sullaRata(Get $get): bool
    {
        return in_array(TipoProdotto::query()->whereKey($get('tipoprodotto_id'))->value('name'), ['Cessione', 'Delega'], true);
    }

    /**
     * Apre la scheda cliente del richiedente (la crea dai dati della pratica se manca).
     */
    private static function schedaClienteAction(): Action
    {
        return Action::make('schedaCliente')
            ->label('Scheda cliente')
            ->tooltip(fn (?Pratica $record): string => $record !== null && self::schedaEsiste($record) ? 'Apri la scheda cliente' : 'Crea la scheda cliente')
            ->icon(Heroicon::OutlinedIdentification)
            ->action(function (Get $get, ?Pratica $record, Component $livewire): void {
                if ($record === null || blank($get('codice_fiscale'))) {
                    Notification::make()->title('Inserisci il codice fiscale o la partita IVA')->warning()->send();

                    return;
                }

                // La scheda nasce dai dati inseriti nel form, anche se la pratica non è ancora stata salvata.
                $record->fill([
                    'codice_fiscale' => $get('codice_fiscale'),
                    'cognome_cliente' => $get('cognome_cliente'),
                    'nome_cliente' => $get('nome_cliente'),
                ]);
                $client = app(RichiedentePratica::class)->ensureClient($record);

                if ($client === null) {
                    return;
                }

                $livewire->redirect(ClientResource::getUrl('edit', ['record' => $client]));
            });
    }

    private static function schedaEsiste(Pratica $pratica): bool
    {
        $code = strtoupper(trim((string) $pratica->codice_fiscale));

        return $code !== '' && Client::query()->where(fn ($query) => $query->where('tax_code', $code)->orWhere('vat_number', $code))->exists();
    }

    /** Controllo con le routine di unico-core: codice fiscale per le persone, partita IVA per le società. */
    private static function taxCodeError(string $value, bool $isPerson): ?string
    {
        return ControlloRichiedente::taxCodeError($value, $isPerson);
    }

    private static function warnAboutTaxCode(?string $state, Get $get): void
    {
        ControlloRichiedente::warn($state, (bool) $get('richiedente_persona_fisica'), $get('cognome_cliente'), $get('nome_cliente'));
    }

    /** Dati societari da Openapi (a pagamento) per la partita IVA indicata. */
    private static function openApiAction(): Action
    {
        return Action::make('openapi')
            ->label('Cerca con Openapi')
            ->tooltip('Cerca la società con Openapi')
            ->icon(Heroicon::OutlinedBuildingOffice2)
            ->visible(fn (Get $get): bool => ! $get('richiedente_persona_fisica'))
            ->requiresConfirmation()
            ->modalHeading('Interrogare Openapi?')
            ->modalDescription('La consultazione di Openapi è a pagamento. Confermi il lancio?')
            ->modalSubmitActionLabel('Interroga')
            ->action(function (Get $get, Set $set): void {
                $vat = strtoupper(trim((string) $get('codice_fiscale')));
                $error = controllaPartitaIva(preg_replace('/^IT/', '', $vat) ?? $vat);

                if ($error !== null) {
                    Notification::make()->title($error)->warning()->send();

                    return;
                }

                try {
                    $company = app(OpenApiCompanyService::class)->fetchCompany($vat)['company'];
                } catch (\Throwable $e) {
                    Notification::make()->title('Openapi')->body($e->getMessage())->danger()->send();

                    return;
                }

                if (filled($company['name'])) {
                    $set('cognome_cliente', $company['name']);
                }

                Notification::make()
                    ->title('Openapi: '.($company['name'] ?? $vat))
                    ->body(collect([
                        $company['legal_form'],
                        $company['address'],
                        $company['pec'],
                        filled($company['ateco']) ? 'Ateco '.$company['ateco'] : null,
                        $company['activity_status'],
                    ])->filter()->implode(' · '))
                    ->success()
                    ->persistent()
                    ->send();
            });
    }
}
