<?php

namespace App\Filament\Resources\Praticas\Schemas;

use App\Models\Pratica;
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
use Unico\Core\Clients\OpenApiCompanyService;
use Unico\Core\Models\Cliente;
use Unico\Core\Support\CodiceFiscale;

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
                                    ->columns(3)
                                    ->schema([
                                        Toggle::make('richiedente_persona_fisica')
                                            ->label('Persona fisica')
                                            ->inline(false)
                                            ->live()
                                            ->dehydrated(false)
                                            ->default(true)
                                            ->afterStateHydrated(fn (Toggle $component, ?Pratica $record) => $component->state(
                                                ! preg_match('/^(IT)?\d{11}$/i', trim((string) $record?->codice_fiscale)),
                                            ))
                                            ->columnSpanFull(),
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
                                            ->suffixAction(self::openApiAction()),
                                    ]),
                                Section::make('Prodotto richiesto')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('codice_pratica')->label('Codice pratica'),
                                        Select::make('fornitori_id')
                                            ->label('Agente')
                                            ->relationship('fornitori', 'name')
                                            ->searchable()
                                            ->preload(),
                                        Select::make('tipoprodotto_id')
                                            ->label('Tipo prodotto')
                                            ->relationship('tipoprodotto', 'name')
                                            ->searchable()
                                            ->preload(),
                                        TextInput::make('denominazione_prodotto')->label('Prodotto'),
                                        DatePicker::make('data_inserimento_pratica')->label('Data richiesta'),
                                        Toggle::make('is_notowned')->label('Impegno di terzi')->inline(false),
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
                                        Select::make('clienti_id')
                                            ->label('Banca')
                                            ->relationship('clienti', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->live()
                                            ->afterStateUpdated(fn (Set $set, ?string $state) => $set('denominazione_banca', $state ? Cliente::query()->whereKey($state)->value('name') : null)),
                                        Hidden::make('denominazione_banca'),
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
                                        TextInput::make('amount')->label('Importo lordo')->numeric()->prefix('€'),
                                        TextInput::make('net')->label('Importo netto')->numeric()->prefix('€'),
                                        TextInput::make('erogato')->label('Erogato')->numeric()->prefix('€'),
                                        TextInput::make('rata')->label('Rata')->numeric()->prefix('€'),
                                        TextInput::make('nrate')->label('Numero rate')->numeric()->integer(),
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

    /** Controllo con le routine di unico-core: codice fiscale per le persone, partita IVA per le società. */
    private static function taxCodeError(string $value, bool $isPerson): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if ($isPerson) {
            return CodiceFiscale::parse($value) === null ? 'Il codice fiscale non è valido.' : null;
        }

        return controllaPartitaIva(strtoupper($value));
    }

    private static function warnAboutTaxCode(?string $state, Get $get): void
    {
        $isPerson = (bool) $get('richiedente_persona_fisica');
        $error = self::taxCodeError((string) $state, $isPerson);

        if ($error !== null) {
            Notification::make()->title($error)->warning()->send();

            return;
        }

        if ($isPerson && filled($state) && filled($get('cognome_cliente')) && filled($get('nome_cliente'))) {
            $mismatches = CodiceFiscale::mismatches((string) $state, (string) $get('cognome_cliente'), (string) $get('nome_cliente'));

            if ($mismatches !== []) {
                Notification::make()
                    ->title('Il codice fiscale non corrisponde a nome e cognome')
                    ->body(collect($mismatches)->map(fn (array $m): string => "{$m['field']}: atteso {$m['expected']}, trovato {$m['found']}")->implode(' · '))
                    ->warning()
                    ->send();
            }
        }
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
