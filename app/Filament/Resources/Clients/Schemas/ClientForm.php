<?php

namespace App\Filament\Resources\Clients\Schemas;

use App\Enums\EmploymentRelationship;
use App\Enums\FinancingSection;
use App\Filament\Resources\Clients\Pages\EditClient;
use App\Models\Client;
use App\Services\AmlScreeningService;
use App\Services\CodiceFiscaleDecoder;
use App\Services\PartitaIva;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
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
use Illuminate\Database\Eloquent\Builder;

class ClientForm
{
    public static function configure(Schema $schema): Schema
    {
        $isPerson = fn (?Get $get = null): bool => $get instanceof Get && (bool) $get('../is_person');

        return $schema
            ->components([
                Section::make()
                    ->columns(3)
                    ->schema([
                        TextInput::make('name')
                            ->label(fn (Get $get): string => $get('is_person') ? 'Cognome' : 'Ragione sociale')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),
                        Toggle::make('is_person')
                            ->label('Persona fisica')
                            ->default(true)
                            ->inline(false)
                            ->live(),
                    ])
                    ->columnSpanFull(),
                Tabs::make('Cliente')
                    ->columnSpanFull()
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make('Anagrafica')
                            ->icon(Heroicon::OutlinedUser)
                            ->schema([
                                Section::make('Dati identificativi')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('first_name')
                                            ->label('Nome')
                                            ->maxLength(255)
                                            ->visible(fn (Get $get): bool => (bool) $get('is_person')),
                                        TextInput::make('tax_code')
                                            ->label(fn (Get $get): string => $get('is_person') ? 'Codice fiscale' : 'Partita IVA')
                                            ->maxLength(16)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn (?string $state, Get $get, Set $set) => self::decodeTaxCode($state, $get, $set)),
                                    ]),
                                Section::make('Contatti')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('email')->label('Email')->email(),
                                        TextInput::make('phone')->label('Telefono')->tel(),
                                        TextInput::make('pec')->label('PEC')->email()->visible(fn (Get $get): bool => ! $get('is_person')),
                                        TextInput::make('website')->label('Sito web')->maxLength(50)->visible(fn (Get $get): bool => ! $get('is_person')),
                                    ]),
                                Section::make('Dati anagrafici')
                                    ->columns(4)
                                    ->visible(fn (Get $get, $livewire): bool => (bool) $get('is_person') && self::shows(FinancingSection::ClienteDatiNascita, $livewire))
                                    ->schema([
                                        DatePicker::make('birth_date')->label('Data di nascita')->native(false)->displayFormat('d/m/Y'),
                                        TextInput::make('birth_place')->label('Luogo di nascita')->maxLength(100),
                                        Select::make('sex')->label('Sesso')->options(['M' => 'M', 'F' => 'F']),
                                        TextInput::make('citizenship')->label('Cittadinanza')->maxLength(60),
                                    ]),
                            ]),
                        Tab::make('Lavoro e reddito')
                            ->icon(Heroicon::OutlinedBriefcase)
                            ->visible(fn (Get $get): bool => (bool) $get('is_person'))
                            ->schema([
                                Section::make('Rapporto di lavoro')
                                    ->columns(2)
                                    ->schema([
                                        Select::make('employment_typology')
                                            ->label('Tipologia')
                                            ->options(collect(EmploymentRelationship::cases())->mapWithKeys(fn (EmploymentRelationship $case): array => [$case->getLabel() => $case->getLabel()])->all())
                                            ->searchable()
                                            ->live()
                                            ->afterStateUpdated(function (?string $state, Set $set): void {
                                                if (self::isRetired($state)) {
                                                    $set('hiring_date', null);
                                                }
                                            }),
                                        DatePicker::make('hiring_date')
                                            ->label('Data di assunzione')
                                            ->native(false)
                                            ->displayFormat('d/m/Y')
                                            ->visible(fn (Get $get): bool => filled($get('employment_typology')) && ! self::isRetired($get('employment_typology'))),
                                        Select::make('employer_id')
                                            ->label('Datore di lavoro')
                                            ->relationship('employer', 'name', fn (Builder $query) => $query->where('is_person', false))
                                            ->searchable()
                                            ->preload()
                                            ->visible(fn ($livewire): bool => self::shows(FinancingSection::ClienteDatoreLavoro, $livewire)),
                                        TextInput::make('profession')->label('Professione'),
                                    ]),
                                Section::make('Reddito e coordinate')
                                    ->columns(3)
                                    ->schema([
                                        TextInput::make('salary')
                                            ->label('Reddito netto mensile')
                                            ->numeric()
                                            ->prefix('€')
                                            ->visible(fn ($livewire): bool => self::shows(FinancingSection::ClienteReddito, $livewire)),
                                        TextInput::make('salary_quote')
                                            ->label('Quota cedibile')
                                            ->numeric()
                                            ->prefix('€')
                                            ->visible(fn ($livewire): bool => self::shows(FinancingSection::ClienteQuotaCedibile, $livewire)),
                                        TextInput::make('iban')
                                            ->label('IBAN')
                                            ->maxLength(34)
                                            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? strtoupper(str_replace(' ', '', $state)) : null),
                                    ]),
                                Section::make('Benestare del datore')
                                    ->collapsed()
                                    ->visible(fn ($livewire): bool => self::shows(FinancingSection::ClienteDatoreLavoro, $livewire))
                                    ->schema([
                                        Textarea::make('richiesta_benestare')->label('Richiesta benestare'),
                                        Textarea::make('ritiro_benestare')->label('Ritiro benestare'),
                                        Textarea::make('notifica')->label('Notifica'),
                                    ]),
                            ]),
                        Tab::make('Dati societari')
                            ->icon(Heroicon::OutlinedBuildingOffice)
                            ->visible(fn (Get $get, $livewire): bool => ! $get('is_person') && self::shows(FinancingSection::ClienteDatiSocietari, $livewire))
                            ->schema([
                                Section::make('Società')
                                    ->columns(3)
                                    ->schema([
                                        Select::make('legal_representative_id')
                                            ->label('Legale rappresentante')
                                            ->relationship('legalRepresentative', 'name', fn (Builder $query) => $query->where('is_person', true))
                                            ->searchable()
                                            ->preload(),
                                        TextInput::make('legal_form')->label('Forma giuridica')->maxLength(100),
                                        TextInput::make('ateco_code')->label('Codice Ateco')->maxLength(10),
                                        TextInput::make('cciaa_registration')->label('Iscrizione CCIAA')->maxLength(60),
                                        TextInput::make('sdi_code')->label('Codice SDI')->maxLength(10),
                                        TextInput::make('activity_status')->label('Stato attività')->maxLength(30),
                                        DatePicker::make('company_started_at')->label('Inizio attività')->native(false)->displayFormat('d/m/Y'),
                                        TextInput::make('iban')->label('IBAN')->maxLength(34),
                                    ]),
                                Section::make('Ultimo bilancio')
                                    ->columns(3)
                                    ->schema([
                                        TextInput::make('balance_year')->label('Anno')->numeric(),
                                        TextInput::make('share_capital')->label('Capitale sociale')->numeric()->prefix('€'),
                                        TextInput::make('turnover')->label('Fatturato')->numeric()->prefix('€'),
                                        TextInput::make('net_worth')->label('Patrimonio netto')->numeric()->prefix('€'),
                                        TextInput::make('employees')->label('Dipendenti')->numeric(),
                                    ]),
                            ]),
                        Tab::make('Verifiche')
                            ->icon(Heroicon::OutlinedShieldCheck)
                            ->visible(fn (Get $get): bool => (bool) $get('is_person'))
                            ->schema([
                                Section::make('Valutazione rischio')
                                    ->columns(3)
                                    ->headerActions([
                                        Action::make('kyc')
                                            ->label('KYC')
                                            ->icon(Heroicon::OutlinedShieldCheck)
                                            ->color('warning')
                                            ->visible(fn (?Client $record): bool => $record !== null)
                                            ->url('#kyc-adeguata-verifica'),
                                        self::amlAction(),
                                    ])
                                    ->schema([
                                        Toggle::make('is_pep')->label('PEP (esposto politicamente)'),
                                        Toggle::make('is_sanctioned')->label('Sanzionato / blacklist'),
                                        Toggle::make('is_art108')->label('Esente art. 108 TUB'),
                                        Toggle::make('is_remote_interaction')->label('Interazione a distanza'),
                                        Toggle::make('is_approved')->label('Approvato'),
                                        Select::make('status')
                                            ->label('Stato verifica cliente')
                                            ->options([
                                                'raccolta_dati' => 'Raccolta dati',
                                                'valutazione_aml' => 'Valutazione AML',
                                                'approvata' => 'Approvata',
                                                'sos_inviata' => 'SOS inviata',
                                                'chiusa' => 'Chiusa',
                                            ]),
                                        DateTimePicker::make('blacklist_at')->label('Data blacklist')->readOnly(),
                                    ]),
                                Section::make('KYC (adeguata verifica)')
                                    ->extraAttributes(['id' => 'kyc-adeguata-verifica'])
                                    ->relationship('questionarioKyc')
                                    ->columns(2)
                                    ->schema([
                                        KycQuestionnaireForm::financingPurpose($isPerson),
                                        KycQuestionnaireForm::riskLevel(),
                                        KycQuestionnaireForm::pepStatus($isPerson),
                                        Textarea::make('notes')->label('Note')->columnSpanFull(),
                                        KycQuestionnaireForm::personSection($isPerson),
                                    ]),
                                Section::make('Consensi privacy')
                                    ->columns(3)
                                    ->schema([
                                        DateTimePicker::make('general_consent_at')->label('Consenso base'),
                                        DateTimePicker::make('privacy_policy_read_at')->label('Informativa letta'),
                                        DateTimePicker::make('consent_special_categories_at')->label('Categorie particolari'),
                                        DateTimePicker::make('consent_marketing_at')->label('Marketing'),
                                        DateTimePicker::make('consent_profiling_at')->label('Profilazione'),
                                        DateTimePicker::make('consent_sic_at')->label('Consenso SIC (CRIF)'),
                                        DateTimePicker::make('consent_group_at')->label('Comunicazione al gruppo'),
                                        DateTimePicker::make('consent_third_parties_at')->label('Comunicazione a terzi'),
                                        Toggle::make('privacy_consent')->label('Consenso privacy attivo'),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    /**
     * Con la scheda aperta dal tab di un cliente si vedono solo i dati pertinenti all'ultima pratica;
     * in creazione si vede tutto.
     */
    private static function shows(FinancingSection $section, mixed $livewire): bool
    {
        return ! $livewire instanceof EditClient || $livewire->showsFinancingSection($section);
    }

    private static function isRetired(mixed $typology): bool
    {
        return collect(EmploymentRelationship::cases())
            ->filter(fn (EmploymentRelationship $relationship): bool => $relationship->isRetiree())
            ->contains(fn (EmploymentRelationship $relationship): bool => $relationship->getLabel() === $typology);
    }

    private static function decodeTaxCode(?string $state, Get $get, Set $set): void
    {
        if (! $get('is_person')) {
            if (filled($state) && ! app(PartitaIva::class)->isValid($state)) {
                Notification::make()->title('Partita IVA non valida')->body('Formato o cifra di controllo errati.')->warning()->send();
            }

            return;
        }

        if (strlen(trim((string) $state)) !== 16) {
            return;
        }

        $decoded = app(CodiceFiscaleDecoder::class)->decodeVerified($state);

        if ($decoded === null) {
            Notification::make()->title('Codice fiscale non valido')->body('Il carattere di controllo non è corretto: dati anagrafici non compilati.')->warning()->send();

            return;
        }

        $set('birth_date', $decoded['birth_date']->format('Y-m-d'));
        $set('sex', $decoded['sex']);
        $set('birth_place', $decoded['birth_place']);
    }

    /** Verifica del nominativo sulle liste PEP (sanctions.io): parte solo da un cliente salvato e senza modifiche pendenti. */
    private static function amlAction(): Action
    {
        return Action::make('verificaPep')
            ->label('AML')
            ->icon(Heroicon::OutlinedShieldExclamation)
            ->mountUsing(function (Action $action, Get $get, ?Client $record): void {
                if ($record === null) {
                    Notification::make()->title('Salva prima il cliente')->warning()->send();
                    $action->cancel();

                    return;
                }

                foreach (['name', 'first_name', 'birth_date'] as $field) {
                    if (self::normalize($get($field)) !== self::normalize($record->getAttribute($field))) {
                        Notification::make()->title('Salva le modifiche prima di interrogare')->warning()->send();
                        $action->cancel();

                        return;
                    }
                }
            })
            ->requiresConfirmation()
            ->modalHeading('Verificare le liste PEP?')
            ->modalDescription('Il nominativo del cliente viene inviato al servizio sanctions.io. Confermi?')
            ->modalSubmitActionLabel('Verifica')
            ->action(function (Get $get, ?Client $record): void {
                $name = trim($get('first_name').' '.$get('name'));

                try {
                    $matches = app(AmlScreeningService::class)->searchPep($name, true, $get('birth_date'), $record);
                } catch (\Throwable $e) {
                    Notification::make()->title('Verifica PEP')->body($e->getMessage())->danger()->send();

                    return;
                }

                if ($matches === []) {
                    Notification::make()->title('Verifica PEP: nessuna corrispondenza')->body($name)->success()->send();

                    return;
                }

                Notification::make()
                    ->title('Verifica PEP: '.count($matches).' possibili corrispondenze')
                    ->body(collect($matches)->take(5)->map(fn (array $match): string => $match['name'].' ('.number_format($match['score'] * 100).'%)'.($match['countries'] !== [] ? ' - '.implode(', ', $match['countries']) : ''))->implode(' · ').'. Verificare a mano prima di spuntare PEP.')
                    ->warning()
                    ->persistent()
                    ->send();
            });
    }

    private static function normalize(mixed $value): string
    {
        return $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : trim((string) ($value ?? ''));
    }
}
