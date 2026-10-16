<?php

namespace App\Filament\Resources\Clients\Schemas;

use App\Enums\ApiProvider;
use App\Enums\EmploymentRelationship;
use App\Enums\FinancingSection;
use App\Filament\Resources\Clients\Pages\EditClient;
use App\Models\Client;
use App\Services\AmlScreeningService;
use App\Services\ApiCallLogger;
use App\Services\CodiceFiscaleDecoder;
use App\Services\CreaUtenteCliente;
use App\Services\Istruttoria\DatoreDiLavoro;
use App\Services\PartitaIva;
use App\Support\ControlloRichiedente;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use RuntimeException;
use Unico\Core\Clients\OpenApiCreditScoreService;
use Unico\Core\Documents\Listeners\AnalyzeUploadedDocument;
use Unico\Core\Enums\DocumentStatus;
use Unico\Core\Models\Document;
use Unico\Core\Models\PdfModule;
use Unico\Core\Pdf\ModulePrinter;
use Unico\Core\Pdf\PdfFormException;
use Unico\Core\Pdf\PracticeModuleData;

class ClientForm
{
    public static function configure(Schema $schema): Schema
    {
        $isPerson = fn (?Get $get = null): bool => $get instanceof Get && (bool) $get('../is_person');

        return $schema
            ->components([
                Section::make()
                    ->columns(['default' => 1, 'md' => 2, 'xl' => 4])
                    ->schema([
                        TextInput::make('name')
                            ->label(fn (Get $get): string => $get('is_person') ? 'Cognome' : 'Ragione sociale')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->columnSpan(fn (Get $get): int => $get('is_person') ? 1 : 2),
                        // Cessione, delega e prestito sono per persone fisiche: se il cliente è legato a uno di questi prodotti l'interruttore resta acceso.
                        Toggle::make('is_person')
                            ->label('Persona fisica')
                            ->default(true)
                            ->inline(false)
                            ->live()
                            ->disabled(fn (?Client $record): bool => self::soloPersonaFisica($record))
                            ->dehydrated()
                            ->helperText(fn (?Client $record): ?string => self::soloPersonaFisica($record) ? 'Legato a cessione, delega o prestito: persona fisica.' : null),
                        TextInput::make('first_name')
                            ->label('Nome')
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->visible(fn (Get $get): bool => (bool) $get('is_person')),
                        TextInput::make('tax_code')
                            ->label(fn (Get $get): string => $get('is_person') ? 'Codice fiscale' : 'Partita IVA')
                            ->maxLength(16)
                            ->live(onBlur: true)
                            ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                $error = ControlloRichiedente::taxCodeError((string) $value, (bool) $get('is_person'));

                                if ($error !== null) {
                                    $fail($error);
                                }
                            })
                            ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                                ControlloRichiedente::warn($state, (bool) $get('is_person'), $get('name'), $get('first_name'));
                                self::decodeTaxCode($state, $get, $set);
                            })
                            ->suffixActions([
                                self::creditScoreAction(),
                            ]),
                    ])
                    ->columnSpanFull(),
                Tabs::make('Cliente')
                    ->columnSpanFull()
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make('Anagrafica')
                            ->icon(Heroicon::OutlinedUser)
                            ->schema([
                                Grid::make(['default' => 1, 'md' => 2])
                                    ->columnSpanFull()
                                    ->schema([
                                        Section::make('Contatti')
                                            ->columns(['default' => 1, 'md' => 2])
                                            ->schema([
                                                TextInput::make('email')->label('Email')->email(),
                                                TextInput::make('phone')->label('Telefono')->tel(),
                                                TextInput::make('pec')->label('PEC')->email()->visible(fn (Get $get): bool => ! $get('is_person')),
                                                TextInput::make('website')->label('Sito web')->maxLength(50)->visible(fn (Get $get): bool => ! $get('is_person')),
                                            ]),
                                        Section::make('Dati anagrafici')
                                            ->columns(['default' => 1, 'md' => 2])
                                            ->visible(fn (Get $get, $livewire): bool => (bool) $get('is_person') && self::shows(FinancingSection::ClienteDatiNascita, $livewire))
                                            ->schema([
                                                DatePicker::make('birth_date')->label('Data di nascita')->native(false)->displayFormat('d/m/Y'),
                                                TextInput::make('birth_place')->label('Luogo di nascita')->maxLength(100),
                                                Select::make('sex')->label('Sesso')->options(['M' => 'M', 'F' => 'F']),
                                                TextInput::make('citizenship')->label('Cittadinanza')->maxLength(60),
                                            ]),
                                    ]),
                            ]),
                        Tab::make('Lavoro e reddito')
                            ->icon(Heroicon::OutlinedBriefcase)
                            ->visible(fn (Get $get): bool => (bool) $get('is_person'))
                            ->schema([
                                Section::make('Rapporto di lavoro')
                                    ->columnSpanFull()
                                    ->columns(['default' => 1, 'md' => 2, 'xl' => 4])
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
                                            ->helperText(fn (?Client $record): ?string => app(DatoreDiLavoro::class)->richiesto($record) ? 'Cessione o delega: indica il datore di lavoro o l\'ente. Eredita il tipo di amministrazione della pratica.' : null)
                                            ->relationship('employer', 'name', fn (Builder $query) => $query->where('is_person', false))
                                            ->searchable()
                                            ->preload()
                                            ->createOptionForm(self::datoreDiLavoroForm())
                                            ->createOptionUsing(fn (array $data, ?Client $record): int => (int) app(DatoreDiLavoro::class)->crea($record, $data)->getKey())
                                            ->visible(fn (?Client $record): bool => app(DatoreDiLavoro::class)->richiesto($record)),
                                        TextInput::make('profession')->label('Professione'),
                                    ]),
                                Section::make('Reddito e coordinate')
                                    ->columnSpanFull()
                                    ->columns(['default' => 1, 'md' => 2, 'xl' => 3])
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
                            ]),
                        Tab::make('Dati societari')
                            ->icon(Heroicon::OutlinedBuildingOffice)
                            ->visible(fn (Get $get, ?Client $record, $livewire): bool => ! $get('is_person') && (self::shows(FinancingSection::ClienteDatiSocietari, $livewire) || self::soloDatore($record)))
                            ->schema([
                                Section::make('Società')
                                    ->columns(['default' => 1, 'md' => 2, 'xl' => 4])
                                    ->schema([
                                        Select::make('legal_representative_id')
                                            ->label('Legale rappresentante')
                                            ->relationship('legalRepresentative', 'name', fn (Builder $query) => $query->where('is_person', true))
                                            ->searchable()
                                            ->preload()
                                            ->hidden(fn (?Client $record): bool => self::soloDatore($record)),
                                        Select::make('employment_typology')
                                            ->label('Tipo amministrazione')
                                            ->helperText('Per un datore di lavoro: ereditato dalla pratica di cessione o delega.')
                                            ->options(collect(EmploymentRelationship::cases())->mapWithKeys(fn (EmploymentRelationship $case): array => [$case->getLabel() => $case->getLabel()])->all())
                                            ->searchable(),
                                        TextInput::make('legal_form')->label('Forma giuridica')->maxLength(100)->hidden(fn (?Client $record): bool => self::soloDatore($record)),
                                        TextInput::make('ateco_code')->label('Codice Ateco')->maxLength(10)->hidden(fn (?Client $record): bool => self::soloDatore($record)),
                                        TextInput::make('cciaa_registration')->label('Iscrizione CCIAA')->maxLength(60)->hidden(fn (?Client $record): bool => self::soloDatore($record)),
                                        TextInput::make('sdi_code')->label('Codice SDI')->maxLength(10)->hidden(fn (?Client $record): bool => self::soloDatore($record)),
                                        TextInput::make('activity_status')->label('Stato attività')->maxLength(30)->hidden(fn (?Client $record): bool => self::soloDatore($record)),
                                        DatePicker::make('company_started_at')->label('Inizio attività')->native(false)->displayFormat('d/m/Y')->hidden(fn (?Client $record): bool => self::soloDatore($record)),
                                        TextInput::make('iban')->label('IBAN')->maxLength(34)->hidden(fn (?Client $record): bool => self::soloDatore($record)),
                                    ]),
                                Grid::make(['default' => 1, 'md' => 2])
                                    ->columnSpanFull()
                                    ->schema([
                                        // Richiesta, ritiro e notifica del benestare: solo per chi è datore di lavoro di almeno un cliente.
                                        Section::make('Benestare del datore')
                                            ->description('Come si chiede il benestare al datore, come si ritira e come si notifica.')
                                            ->visible(fn (?Client $record): bool => $record?->exists === true && $record->isDatoreDiLavoro())
                                            ->schema([
                                                Textarea::make('richiesta_benestare')->label('Richiesta benestare'),
                                                Textarea::make('ritiro_benestare')->label('Ritiro benestare'),
                                                Textarea::make('notifica')->label('Notifica'),
                                            ]),
                                        Section::make('Ultimo bilancio')
                                            ->hidden(fn (?Client $record): bool => self::soloDatore($record))
                                            ->columns(['default' => 1, 'md' => 2, 'xl' => 3])
                                            ->schema([
                                                TextInput::make('balance_year')->label('Anno')->numeric(),
                                                TextInput::make('share_capital')->label('Capitale sociale')->numeric()->prefix('€'),
                                                TextInput::make('turnover')->label('Fatturato')->numeric()->prefix('€'),
                                                TextInput::make('net_worth')->label('Patrimonio netto')->numeric()->prefix('€'),
                                                TextInput::make('employees')->label('Dipendenti')->numeric(),
                                            ]),
                                    ]),
                            ]),
                        Tab::make('Verifiche')
                            ->icon(Heroicon::OutlinedShieldCheck)
                            ->hidden(fn (?Client $record): bool => self::soloDatore($record))
                            ->schema([
                                Section::make('KYC (adeguata verifica)')
                                    ->key('kyc')
                                    ->extraAttributes(['id' => 'kyc-adeguata-verifica'])
                                    ->relationship('questionarioKyc')
                                    ->columnSpanFull()
                                    ->headerActions([
                                        self::stampaModuloKycAction(),
                                    ])
                                    ->columns(['default' => 1, 'md' => 2, 'xl' => 3])
                                    ->schema([
                                        KycQuestionnaireForm::financingPurpose($isPerson),
                                        KycQuestionnaireForm::riskLevel(),
                                        KycQuestionnaireForm::pepStatus($isPerson),
                                        Textarea::make('notes')->label('Note')->columnSpanFull(),
                                        KycQuestionnaireForm::personSection($isPerson),
                                        Placeholder::make('kyc_datore_di_lavoro')
                                            ->label('Datore di lavoro')
                                            ->content(fn (mixed $livewire): string => self::clienteDellaScheda($livewire)?->employer?->name ?? 'Non indicato: si imposta in «Lavoro e reddito».')
                                            ->visible(fn (mixed $livewire): bool => app(DatoreDiLavoro::class)->richiesto(self::clienteDellaScheda($livewire)))
                                            ->columnSpanFull(),
                                    ]),
                                Section::make('Valutazione rischio')
                                    ->columnSpanFull()
                                    ->columns(['default' => 1, 'md' => 2, 'xl' => 4])
                                    ->headerActions([
                                        self::amlAction(),
                                    ])
                                    ->schema([
                                        Toggle::make('is_pep')->label('PEP (esposto politicamente)')->visible(fn (Get $get): bool => (bool) $get('is_person')),
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
                            ]),
                        Tab::make('Info')
                            ->icon(Heroicon::OutlinedInformationCircle)
                            ->schema([
                                Section::make('Consensi privacy')
                                    ->visible(fn (Get $get): bool => (bool) $get('is_person'))
                                    ->columnSpanFull()
                                    ->columns(['default' => 1, 'md' => 2, 'xl' => 4, '2xl' => 6])
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
                                Section::make('Lead')
                                    ->columnSpanFull()
                                    ->columns(['default' => 1, 'md' => 2])
                                    ->schema([
                                        Toggle::make('is_lead')->label('Lead non ancora convertito')->inline(false),
                                        DateTimePicker::make('acquired_at')->label('Data di acquisizione'),
                                    ]),
                                Section::make('Utente collegato')
                                    ->columnSpanFull()
                                    ->schema([
                                        Select::make('user_id')
                                            ->label('Utente di accesso')
                                            ->relationship('user', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->placeholder('Nessun utente')
                                            ->suffixAction(self::creaUtenteAction()),
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

    /** Datore di lavoro (ha clienti con ruolo dipendente): dei dati societari servono la tipologia e il benestare, non la scheda aziendale né le verifiche. */
    private static function soloDatore(?Client $record): bool
    {
        return $record?->isDatoreDiLavoro() === true;
    }

    /** Stampa il modulo QAV (questionario di adeguata verifica) del cliente, compilato con i dati della sua ultima pratica. */
    private static function stampaModuloKycAction(): Action
    {
        return Action::make('stampaModuloKyc')
            ->label('Stampa modulo KYC')
            ->icon(Heroicon::OutlinedPrinter)
            ->color('gray')
            ->visible(fn (mixed $livewire): bool => self::clienteDellaScheda($livewire)?->exists === true)
            ->action(function (mixed $livewire) {
                $record = self::clienteDellaScheda($livewire);

                $modulo = PdfModule::query()->active()->where('name', 'like', $record->is_person ? 'QAV Persona fisica%' : 'QAV Persona giuridica%')->first();
                $pratica = $record->ultimaPratica;

                if ($modulo === null || $pratica === null) {
                    Notification::make()->title('Modulo KYC non stampabile')->body($modulo === null ? 'Non c\'è un modulo QAV attivo per questo tipo di cliente.' : 'Il modulo si compila con i dati di una pratica: il cliente non ne ha.')->warning()->send();

                    return null;
                }

                try {
                    $contenuto = app(ModulePrinter::class)->print($pratica, $modulo);
                } catch (PdfFormException $e) {
                    report($e);
                    Notification::make()->title('Stampa non disponibile')->body($e->getMessage())->danger()->send();

                    return null;
                }

                self::archiviaModulo($record, $modulo, $contenuto);

                if ($record->is_person && app(PracticeModuleData::class)->identityDocument($record->loadMissing('documents.documentType')) === null) {
                    Notification::make()->title('Documento d\'identità mancante')->body('Il modulo KYC è stato stampato senza tipo, numero e scadenza del documento: caricalo tra i documenti del cliente.')->warning()->persistent()->send();
                }

                return response()->streamDownload(fn () => print ($contenuto), 'kyc-'.Str::slug((string) $record->name).'.pdf', ['Content-Type' => 'application/pdf']);
            });
    }

    /** Il modulo stampato resta tra i documenti del cliente, al posto della stampa precedente dello stesso modulo. */
    private static function archiviaModulo(Client $cliente, PdfModule $modulo, string $contenuto): void
    {
        $tipo = $modulo->documentType;

        if ($tipo === null) {
            return;
        }

        $documento = Document::query()->firstOrCreate(
            ['documentable_type' => 'client', 'documentable_id' => (string) $cliente->getKey(), 'document_type_id' => $tipo->getKey()],
            ['company_id' => $cliente->company_id, 'name' => $tipo->name, 'status' => DocumentStatus::PENDING],
        );

        AnalyzeUploadedDocument::suspendedWhile(function () use ($documento, $contenuto, $tipo): void {
            $documento->clearMediaCollection(Document::COLLECTION);
            $documento->addMediaFromString($contenuto)
                ->usingFileName((Str::slug((string) $tipo->name) ?: 'modulo').'.pdf')
                ->toMediaCollection(Document::COLLECTION);
        });

        $documento->forceFill(['status' => DocumentStatus::UPLOADED, 'is_printable' => true])->saveQuietly();
    }

    /** Il cliente della scheda: nelle sezioni legate a una relazione (es. il KYC) `$record` è il record della relazione. */
    private static function clienteDellaScheda(mixed $livewire): ?Client
    {
        $record = method_exists($livewire, 'getRecord') ? $livewire->getRecord() : null;

        return $record instanceof Client ? $record : null;
    }

    /** Crea l'utente di accesso della persona fisica e lo collega alla scheda. */
    private static function creaUtenteAction(): Action
    {
        return Action::make('creaUtente')
            ->label('Crea utente')
            ->icon(Heroicon::OutlinedUserPlus)
            ->visible(fn (?Client $record, Get $get): bool => $record?->exists === true && (bool) $get('is_person') && blank($record->user_id))
            ->requiresConfirmation()
            ->modalHeading('Crea utente')
            ->modalDescription('Viene creato l\'utente con l\'email salvata sulla scheda e collegato al cliente. La password la imposta lui con il recupero password.')
            ->action(function (?Client $record, Set $set): void {
                try {
                    $utente = app(CreaUtenteCliente::class)->crea($record);
                } catch (RuntimeException $e) {
                    Notification::make()->title('Utente non creato')->body($e->getMessage())->warning()->send();

                    return;
                }

                $set('user_id', $utente->getKey());
                Notification::make()->title('Utente collegato')->body($utente->email)->success()->send();
            });
    }

    private static function isRetired(mixed $typology): bool
    {
        return collect(EmploymentRelationship::cases())
            ->filter(fn (EmploymentRelationship $relationship): bool => $relationship->isRetiree())
            ->contains(fn (EmploymentRelationship $relationship): bool => $relationship->getLabel() === $typology);
    }

    /** @return array<int, TextInput> */
    public static function datoreDiLavoroForm(): array
    {
        return [
            TextInput::make('name')->label('Ragione sociale')->required()->maxLength(255),
            TextInput::make('tax_code')
                ->label('Partita IVA')
                ->maxLength(16)
                ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                    $errore = ControlloRichiedente::taxCodeError((string) $value, false);

                    if ($errore !== null) {
                        $fail($errore);
                    }
                }),
        ];
    }

    /** Cessione, delega e prestito sono prodotti per persone fisiche. */
    private static function soloPersonaFisica(?Client $record): bool
    {
        return $record?->exists === true
            && $record->pratiche()->whereHas('tipoprodotto', fn (Builder $query) => $query->whereIn('name', ['Cessione', 'Delega', 'Prestito']))->exists();
    }

    /**
     * Credit score da Openapi (GET /IT-creditscore-top/{codice fiscale}): per ora la richiesta è simulata,
     * senza chiamata, e si conferma con una notifica che il servizio si sta attivando.
     */
    private static function creditScoreAction(): Action
    {
        return Action::make('creditScore')
            ->label('Credit score')
            ->tooltip('Richiedi il credit score a Openapi')
            ->icon(Heroicon::OutlinedChartBar)
            ->requiresConfirmation()
            ->modalHeading('Richiedere il credit score?')
            ->modalDescription('Il credit score viene richiesto a Openapi con il codice fiscale del cliente. Per ora la richiesta è simulata: non parte nessuna chiamata e non si paga.')
            ->modalSubmitActionLabel('Richiedi')
            ->action(function (Get $get, ?Client $record): void {
                $taxCode = strtoupper(preg_replace('/\s+/', '', (string) $get('tax_code')));
                $error = ControlloRichiedente::taxCodeError($taxCode, (bool) $get('is_person'));

                if ($taxCode === '' || $error !== null) {
                    Notification::make()->title($error ?? 'Inserire prima il codice fiscale o la partita IVA')->warning()->send();

                    return;
                }

                $startedAt = microtime(true);

                try {
                    $result = app(OpenApiCreditScoreService::class)->request($taxCode);
                } catch (\Throwable $e) {
                    Notification::make()->title('Credit score')->body($e->getMessage())->danger()->send();

                    return;
                }

                app(ApiCallLogger::class)->record(
                    ApiProvider::OpenapiCreditScore,
                    'creditscore',
                    $record,
                    ['method' => $result['method'], 'url' => $result['url']],
                    $startedAt,
                    null,
                    true,
                    'Richiesta simulata',
                    cost: 0,
                    response: ['simulated' => true],
                );

                Notification::make()
                    ->title('Credit score: richiesta attivata')
                    ->body("Simulazione: nessuna chiamata effettuata. Verrà interrogato {$result['method']} {$result['url']}")
                    ->success()
                    ->persistent()
                    ->send();
            });
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
