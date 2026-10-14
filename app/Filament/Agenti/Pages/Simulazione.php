<?php

namespace App\Filament\Agenti\Pages;

use App\Enums\EmploymentRelationship;
use App\Enums\KycStatus;
use App\Enums\SimulationContractType;
use App\Filament\Resources\Clients\Schemas\KycQuestionnaireForm;
use App\Models\FinancingQuote;
use App\Models\KycQuestionnaire;
use App\Models\User;
use App\Services\CodiceFiscaleDecoder;
use App\Services\Preventivazione\PracticeDocumentUploader;
use App\Services\Preventivazione\PracticeFinalizer;
use App\Services\Preventivazione\ProductTaskDocuments;
use App\Services\Preventivazione\QuotationService;
use App\Services\Preventivazione\QuoteUnavailable;
use App\Support\FinancingClientFields;
use BackedEnum;
use Carbon\CarbonImmutable;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Unico\Core\Models\Client;
use Unico\Core\Models\Document;
use Unico\Core\Models\Fornitore;
use Unico\Core\Models\Pratica;
use Unico\Core\Models\PraticaClient;

/**
 * Wizard: dati della persona, dati del finanziamento, preventivazione, salva o perfeziona.
 *
 * @property-read Schema $form
 */
class Simulazione extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationLabel = 'Simulazione';

    protected static ?string $title = 'Simulazione';

    protected static ?string $slug = 'simulazione';

    protected static ?int $navigationSort = -1;

    protected string $view = 'filament.agenti.pages.simulazione';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /**
     * @var array<string, mixed>|null
     */
    public ?array $profilo = null;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $bozza = null;

    /**
     * @var array{min: float, max: float}|null
     */
    public ?array $esito = null;

    public bool $preventivatoreAssente = false;

    public ?string $errorePreventivo = null;

    /** preventivo | informativa | contatti | perfezionare | cliente | cliente_salvato */
    public ?string $fase = null;

    public ?int $quoteId = null;

    public ?int $preventivoNumero = null;

    public ?string $codicePratica = null;

    public ?string $statoPratica = null;

    /**
     * Informativa privacy da scaricare e far firmare al cliente prima di ogni dato personale.
     *
     * @var list<array{document_type_id: int, name: string, required: bool, url: ?string}>
     */
    public array $informativaRichiesta = [];

    /**
     * @var array<string, mixed>
     */
    public array $informativaAllegati = [];

    /**
     * Controlli dell'informativa non superati: avvisano l'agente, ma non bloccano il perfezionamento.
     *
     * @var list<string>
     */
    public array $avvisiInformativa = [];

    /**
     * Email e cellulare del cliente, chiesti prima di perfezionare e scritti sul cliente quando si salva.
     *
     * @var array<string, mixed>
     */
    public array $contatti = [];

    /**
     * @var list<array{document_type_id: int, name: string, required: bool}>
     */
    public array $documentiRichiesti = [];

    /**
     * @var list<array{file: string, tipo: ?string}>
     */
    public array $esitiDocumenti = [];

    /**
     * @var array<string, mixed>
     */
    public array $allegati = [];

    /**
     * @var list<string>
     */
    public array $campiCliente = [];

    /**
     * @var array<string, mixed>
     */
    public array $cliente = [];

    /**
     * @var list<string>
     */
    public array $documentiMancanti = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make([
                    Step::make('Dati della persona')
                        ->icon('heroicon-o-user')
                        ->schema([
                            ToggleButtons::make('tipo_contratto')
                                ->label('Tipo contratto')
                                ->options(SimulationContractType::class)
                                ->inline()
                                ->required()
                                ->live()
                                ->afterStateUpdated(function (Set $set, Get $get, mixed $state): void {
                                    $relationship = self::relationshipFrom($get('tipo_rapporto'));
                                    $contract = self::contractTypeFrom($state);

                                    if ($relationship?->isRetiree() && $contract?->allowsRetirees() === false) {
                                        $set('tipo_rapporto', null);
                                    }

                                    if ($contract?->asksQuotaCedibile() === true) {
                                        $set('importo_richiesto', null);
                                    } else {
                                        $set('quota_cedibile', null);
                                    }

                                    if ($contract !== null && ! in_array((int) $get('durata'), $contract->durationChoices(), true)) {
                                        $set('durata', $contract->defaultDuration());
                                    }
                                }),
                            ToggleButtons::make('sesso')
                                ->label('Sesso')
                                ->options(['M' => 'M', 'F' => 'F'])
                                ->inline()
                                ->required(),
                            DatePicker::make('data_nascita')
                                ->label('Data di nascita')
                                ->native(false)
                                ->displayFormat('d/m/Y')
                                ->maxDate(today()->subYears(18))
                                ->required(),
                            Select::make('tipo_rapporto')
                                ->label('Tipo amministrazione')
                                ->options(fn (Get $get): array => EmploymentRelationship::groupedOptions(
                                    self::contractTypeFrom($get('tipo_contratto')),
                                ))
                                ->in(fn (Get $get): array => array_column(
                                    EmploymentRelationship::allowedFor(self::contractTypeFrom($get('tipo_contratto'))),
                                    'value',
                                ))
                                ->helperText(fn (Get $get): ?string => self::contractTypeFrom($get('tipo_contratto'))?->allowsRetirees() === false
                                    ? 'La delega è riservata ai dipendenti.'
                                    : null)
                                ->native(false)
                                ->required()
                                ->live(),
                            DatePicker::make('data_assunzione')
                                ->label('Data assunzione')
                                ->native(false)
                                ->displayFormat('d/m/Y')
                                ->maxDate(today())
                                ->after('data_nascita')
                                ->visible(fn (Get $get): bool => self::relationshipFrom($get('tipo_rapporto'))?->isRetiree() === false)
                                ->required(fn (Get $get): bool => self::relationshipFrom($get('tipo_rapporto'))?->isRetiree() === false),
                        ]),
                    Step::make('Dati del finanziamento')
                        ->icon('heroicon-o-banknotes')
                        ->schema([
                            Select::make('durata')
                                ->label('Durata')
                                ->options(fn (Get $get): array => self::contractTypeFrom($get('tipo_contratto'))?->durationOptions() ?? [])
                                ->in(fn (Get $get): array => self::contractTypeFrom($get('tipo_contratto'))?->durationChoices() ?? [])
                                ->native(false)
                                ->required(),
                            TextInput::make('quota_cedibile')
                                ->label('Quota cedibile')
                                ->numeric()
                                ->inputMode('decimal')
                                ->prefix('€')
                                ->minValue(0)
                                ->visible(fn (Get $get): bool => self::contractTypeFrom($get('tipo_contratto'))?->asksQuotaCedibile() === true)
                                ->required(fn (Get $get): bool => self::contractTypeFrom($get('tipo_contratto'))?->asksQuotaCedibile() === true),
                            TextInput::make('importo_richiesto')
                                ->label('Importo richiesto')
                                ->numeric()
                                ->inputMode('decimal')
                                ->prefix('€')
                                ->minValue(0)
                                ->visible(fn (Get $get): bool => self::contractTypeFrom($get('tipo_contratto'))?->asksQuotaCedibile() === false)
                                ->required(fn (Get $get): bool => self::contractTypeFrom($get('tipo_contratto'))?->asksQuotaCedibile() === false),
                            DatePicker::make('data_decorrenza')
                                ->label('Data decorrenza')
                                ->native(false)
                                ->displayFormat('d/m/Y')
                                ->default(fn (): string => today()->addMonthsNoOverflow(3)->toDateString())
                                ->minDate(today())
                                ->helperText('Proposta a 3 mesi dalla data odierna.')
                                ->required(),
                        ]),
                ])
                    ->submitAction(new HtmlString(Blade::render(<<<'BLADE'
                        <x-filament::button type="submit" icon="heroicon-o-calculator">
                            Preventiva
                        </x-filament::button>
                    BLADE))),
            ])
            ->statePath('data');
    }

    public function simulate(QuotationService $quotation): void
    {
        $state = $this->form->getState();

        $contract = self::contractTypeFrom($state['tipo_contratto']);
        $relationship = self::relationshipFrom($state['tipo_rapporto']);
        $decorrenza = CarbonImmutable::parse($state['data_decorrenza']);
        $assunzione = filled($state['data_assunzione'] ?? null) ? CarbonImmutable::parse($state['data_assunzione']) : null;
        $nascita = CarbonImmutable::parse($state['data_nascita']);

        $quota = $contract->asksQuotaCedibile() ? (float) $state['quota_cedibile'] : null;
        $importo = $contract->asksQuotaCedibile() ? null : (float) $state['importo_richiesto'];

        $this->bozza = [
            'contract_type' => $contract->value,
            'sex' => $state['sesso'],
            'birth_date' => $nascita->toDateString(),
            'employment_relationship' => $relationship->value,
            'hired_at' => $assunzione?->toDateString(),
            'starts_at' => $decorrenza->toDateString(),
            'duration_months' => $contract->durationInMonths((int) $state['durata']),
            'assignable_quota' => $quota,
            'requested_amount' => $importo,
            'age_at_start' => (int) $nascita->diffInYears($decorrenza),
            'seniority_months' => $assunzione ? (int) $assunzione->diffInMonths($decorrenza) : null,
        ];
        $this->profilo = [
            'tipo_contratto' => $contract->getLabel(),
            'sesso' => $state['sesso'],
            'tipo_rapporto' => $relationship->getLabel(),
            'durata_etichetta' => $contract->durationLabel((int) $state['durata']),
            'quota_cedibile' => $quota,
            'importo_richiesto' => $importo,
            'data_decorrenza' => $decorrenza->format('d/m/Y'),
        ];
        $this->fase = null;
        $this->quoteId = null;
        $this->preventivoNumero = null;
        $this->codicePratica = null;
        $this->statoPratica = null;
        $this->documentiRichiesti = [];
        $this->informativaRichiesta = [];
        $this->informativaAllegati = [];
        $this->avvisiInformativa = [];
        $this->contatti = [];
        $this->esitiDocumenti = [];
        $this->allegati = [];
        $this->campiCliente = [];
        $this->cliente = [];
        $this->documentiMancanti = [];
        $this->esito = null;
        $this->errorePreventivo = null;
        $this->preventivatoreAssente = blank(config('preventivatore.url'));

        if ($this->preventivatoreAssente) {
            return;
        }

        try {
            $result = $quotation->quote($this->bozza);
        } catch (QuoteUnavailable $e) {
            $this->errorePreventivo = $e->getMessage();
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        $this->esito = ['min' => $result['min'], 'max' => $result['max']];
    }

    public function salvaPreventivo(PracticeFinalizer $finalizer): void
    {
        $user = Auth::user();
        $fornitore = $this->fornitore();

        if ($this->bozza === null || $this->fase !== null || ! $user instanceof User || $fornitore === null) {
            return;
        }

        if ($this->esito === null && ! $this->preventivatoreAssente) {
            return;
        }

        try {
            $quote = $finalizer->saveQuote($fornitore, $this->bozza, $this->esito ?? ['min' => null, 'max' => null], $user);
        } catch (QuoteUnavailable $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        $this->rememberQuote($quote, 'PREVENTIVO', 'preventivo');
    }

    /**
     * Il perfezionamento parte dall'informativa privacy (task dedicato): si scarica, il cliente la firma
     * con firma olografa e l'agente la carica. Senza task configurato si passa ai contatti.
     */
    public function perfeziona(ProductTaskDocuments $documents): void
    {
        if ($this->fase !== 'preventivo' || $this->quoteId === null) {
            return;
        }

        $this->informativaRichiesta = $documents->privacy()
            ->filter(fn ($requirement): bool => $requirement->documentType !== null)
            ->map(fn ($requirement): array => [
                'document_type_id' => (int) $requirement->document_type_id,
                'name' => (string) $requirement->documentType->name,
                'required' => (bool) $requirement->is_required,
                'url' => filled($requirement->documentType->document_url) ? (string) $requirement->documentType->document_url : null,
            ])
            ->values()
            ->all();

        if ($this->informativaRichiesta === []) {
            $this->apriContatti();

            return;
        }

        $this->fase = 'informativa';
    }

    public function informativaForm(Schema $schema): Schema
    {
        $fields = [];

        foreach ($this->informativaRichiesta as $document) {
            $fields[] = FileUpload::make((string) $document['document_type_id'])
                ->label($document['name'])
                ->required($document['required'])
                ->storeFiles(false)
                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                ->maxSize(10240);
        }

        return $schema->components($fields)->statePath('informativaAllegati');
    }

    public function caricaInformativa(PracticeDocumentUploader $uploader): void
    {
        if ($this->fase !== 'informativa' || $this->quoteId === null) {
            return;
        }

        $state = $this->informativaForm->getState();
        $pratica = FinancingQuote::query()->find($this->quoteId)?->pratica;
        $user = Auth::user();

        if ($pratica === null || ! $user instanceof User) {
            return;
        }

        $candidateIds = array_column($this->informativaRichiesta, 'document_type_id');
        $this->avvisiInformativa = [];

        foreach ($this->informativaRichiesta as $document) {
            $file = $this->uploadedFile($state[(string) $document['document_type_id']] ?? $state[$document['document_type_id']] ?? null);

            if ($file === null) {
                continue;
            }

            $stored = $uploader->store($pratica, $file, $candidateIds, $user, (int) $document['document_type_id']);

            foreach ($this->esitoControlli($stored) as $avviso) {
                $this->avvisiInformativa[] = $document['name'].': '.$avviso;
            }
        }

        if ($this->avvisiInformativa !== []) {
            Notification::make()
                ->title('Informativa da verificare')
                ->body(implode("\n", $this->avvisiInformativa))
                ->warning()
                ->send();
        }

        $this->apriContatti();
    }

    private function apriContatti(): void
    {
        $this->fase = 'contatti';
        $this->contattiForm->fill($this->contatti);
    }

    /**
     * Problemi trovati dai controlli di unico-core sul documento, oppure l'avviso che resta da verificare.
     *
     * @return list<string>
     */
    private function esitoControlli(Document $document): array
    {
        $analysis = $document->analyses()->where('kind', 'extraction')->latest('id')->first();

        if ($analysis === null || $analysis->outcome === 'passed') {
            return $analysis === null ? ['non controllato automaticamente: lo verifica il back office.'] : [];
        }

        $issues = array_values(array_filter((array) ($analysis->result['issues'] ?? []), 'is_string'));

        return $issues !== [] ? $issues : ['non è stato possibile controllarlo: lo verifica il back office.'];
    }

    public function contattiForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('email')
                    ->label('Email del cliente')
                    ->email()
                    ->required()
                    ->maxLength(255),
                TextInput::make('phone')
                    ->label('Cellulare del cliente')
                    ->tel()
                    ->required()
                    ->regex('/^\+?[0-9 .\-\/]{6,20}$/')
                    ->validationMessages(['regex' => 'Inserisci un numero di cellulare valido.'])
                    ->maxLength(50),
            ])
            ->columns(2)
            ->statePath('contatti');
    }

    public function confermaContatti(PracticeFinalizer $finalizer, ProductTaskDocuments $documents): void
    {
        if ($this->fase !== 'contatti' || $this->quoteId === null) {
            return;
        }

        $this->contatti = $this->contattiForm->getState();

        $quote = FinancingQuote::query()->find($this->quoteId);

        if ($quote === null) {
            return;
        }

        try {
            $quote = $finalizer->perfect($quote);
        } catch (QuoteUnavailable $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        $productName = SimulationContractType::from($quote->contract_type)->productName();
        $this->documentiRichiesti = $documents->forProductStatus($productName, 'PERFEZIONARE')
            // I documenti d'identità (letti dall'AI) per primi: servono a compilare l'anagrafica del cliente.
            ->sortBy(fn ($requirement): int => $requirement->documentType?->ai_kind === null ? 1 : 0)
            ->map(fn ($requirement): array => [
                'document_type_id' => (int) $requirement->document_type_id,
                'name' => (string) $requirement->documentType?->name,
                'required' => (bool) $requirement->is_required,
            ])
            ->values()
            ->all();
        $this->rememberQuote($quote, 'PERFEZIONARE', 'perfezionare');
    }

    public function uploadsForm(Schema $schema): Schema
    {
        $fields = [];

        foreach ($this->documentiRichiesti as $document) {
            $fields[] = FileUpload::make((string) $document['document_type_id'])
                ->label($document['name'])
                ->required($document['required'])
                ->storeFiles(false)
                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                ->maxSize(10240);
        }

        return $schema->components($fields)->statePath('allegati');
    }

    public function caricaDocumenti(PracticeDocumentUploader $uploader): void
    {
        if ($this->fase !== 'perfezionare' || $this->quoteId === null) {
            return;
        }

        $state = $this->uploadsForm->getState();
        $quote = FinancingQuote::query()->find($this->quoteId);
        $pratica = $quote?->pratica;
        $user = Auth::user();

        if ($pratica === null || ! $user instanceof User) {
            return;
        }

        $candidateIds = array_column($this->documentiRichiesti, 'document_type_id');

        foreach ($this->documentiRichiesti as $document) {
            $file = $this->uploadedFile($state[(string) $document['document_type_id']] ?? $state[$document['document_type_id']] ?? null);

            if ($file === null) {
                continue;
            }

            $stored = $uploader->store($pratica, $file, $candidateIds, $user, (int) $document['document_type_id']);
            $this->esitiDocumenti[] = [
                'file' => $file->getClientOriginalName(),
                'tipo' => $stored->documentType?->name,
            ];
        }

        $this->documentiMancanti = $this->tipiMancanti($pratica);

        if ($this->documentiMancanti !== []) {
            return;
        }

        $this->apriCliente($pratica);
    }

    public function clienteForm(Schema $schema): Schema
    {
        $fields = [
            'first_name' => TextInput::make('first_name')->label('Nome')->required()->maxLength(100),
            'name' => TextInput::make('name')->label('Cognome')->required()->maxLength(191),
            'tax_code' => TextInput::make('tax_code')
                ->label('Codice fiscale')
                ->required()
                ->length(16)
                ->live(onBlur: true)
                ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? strtoupper(trim($state)) : null)
                ->afterStateUpdated(function (?string $state, Set $set): void {
                    $this->compilaDaCodiceFiscale($state, $set);
                })
                ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                    if (app(CodiceFiscaleDecoder::class)->decodeVerified(is_string($value) ? $value : null) === null) {
                        $fail('Codice fiscale non valido.');
                    }
                }),
            'birth_date' => DatePicker::make('birth_date')
                ->label('Data di nascita')
                ->native(false)
                ->displayFormat('d/m/Y')
                ->disabled()
                ->dehydrated()
                ->helperText('Ricavata dal codice fiscale'),
            'birth_place' => TextInput::make('birth_place')
                ->label('Luogo di nascita')
                ->maxLength(100)
                ->disabled()
                ->dehydrated()
                ->helperText('Ricavato dal codice fiscale'),
            'sex' => ToggleButtons::make('sex')->label('Sesso')->options(['M' => 'M', 'F' => 'F'])->inline()->disabled()->dehydrated(),
            'citizenship' => TextInput::make('citizenship')->label('Cittadinanza')->maxLength(60),
            'employer_id' => Select::make('employer_id')
                ->label('Datore di lavoro')
                ->options(fn (): array => Client::query()->where('is_person', false)->orderBy('name')->pluck('name', 'id')->all())
                ->searchable()
                ->required()
                ->createOptionForm([
                    TextInput::make('name')->label('Ragione sociale')->required()->maxLength(255),
                ])
                ->createOptionUsing(function (array $data): int {
                    $quote = FinancingQuote::query()->find($this->quoteId);

                    return (int) Client::query()->create([
                        'name' => $data['name'],
                        'is_person' => false,
                        'company_id' => $quote?->pratica?->company_id,
                    ])->getKey();
                }),
            'hiring_date' => DatePicker::make('hiring_date')->label('Data di assunzione')->native(false)->displayFormat('d/m/Y'),
            'salary' => TextInput::make('salary')->label('Reddito netto mensile')->numeric()->prefix('€'),
            'salary_quote' => TextInput::make('salary_quote')->label('Quota cedibile')->numeric()->prefix('€'),
        ];

        $visible = array_values(array_intersect(array_keys($fields), $this->campiCliente));

        $isPerson = fn (?Get $get = null): bool => true;

        return $schema
            ->components([
                ...array_map(fn (string $name) => $fields[$name], $visible),
                Section::make('Residenza')
                    ->statePath('residenza')
                    ->columns(3)
                    ->schema([
                        TextInput::make('street')->label('Via, piazza')->required()->maxLength(255)->columnSpan(2),
                        TextInput::make('numero')->label('Numero civico')->maxLength(20),
                        TextInput::make('zip_code')->label('CAP')->required()->regex('/^\d{5}$/')->validationMessages(['regex' => 'Il CAP ha 5 cifre.']),
                        TextInput::make('city')->label('Città')->required()->maxLength(100),
                        TextInput::make('province')->label('Provincia (sigla)')->required()->length(2)
                            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? strtoupper(trim($state)) : null),
                    ]),
                Section::make('Adeguata verifica (KYC)')
                    ->statePath('kyc')
                    ->columns(2)
                    ->schema([
                        KycQuestionnaireForm::pepStatus($isPerson, true),
                        KycQuestionnaireForm::financingPurpose($isPerson, true),
                        KycQuestionnaireForm::personSection($isPerson, true),
                    ]),
                Section::make('Consensi privacy del cliente')
                    ->description('Dichiarazione di consenso al trattamento dei dati: i consensi non dati restano vuoti.')
                    ->statePath('consensi')
                    ->columns(2)
                    ->schema([
                        Toggle::make('marketing')->label('Promozione di nuovi prodotti e servizi del Titolare'),
                        Toggle::make('group')->label('Promozione di prodotti di società del Gruppo'),
                        Toggle::make('profiling')->label('Rilevazione della soddisfazione e delle preferenze'),
                        Toggle::make('third_parties')->label('Promozione di prodotti di società terze'),
                    ]),
            ])
            ->statePath('cliente');
    }

    public function salvaCliente(): void
    {
        if ($this->fase !== 'cliente' || $this->quoteId === null) {
            return;
        }

        $state = $this->clienteForm->getState();
        $decoded = app(CodiceFiscaleDecoder::class)->decodeVerified($state['tax_code'] ?? null);

        if ($decoded === null) {
            Notification::make()->title('Codice fiscale non valido')->warning()->send();

            return;
        }

        $state['birth_date'] = $decoded['birth_date']->toDateString();
        $state['birth_place'] = $decoded['birth_place'];
        $state['sex'] = $decoded['sex'];

        $residenza = Arr::pull($state, 'residenza', []);
        $kyc = Arr::pull($state, 'kyc', []);
        $consensi = Arr::pull($state, 'consensi', []);

        $quote = FinancingQuote::query()->find($this->quoteId);
        $pratica = $quote?->pratica;

        if ($pratica === null) {
            return;
        }

        $client = Client::query()->create([
            'company_id' => $pratica->company_id,
            'is_person' => true,
            'email' => $this->contatti['email'] ?? null,
            'phone' => $this->contatti['phone'] ?? null,
            'consent_marketing_at' => ($consensi['marketing'] ?? false) ? now() : null,
            'consent_group_at' => ($consensi['group'] ?? false) ? now() : null,
            'consent_profiling_at' => ($consensi['profiling'] ?? false) ? now() : null,
            'consent_third_parties_at' => ($consensi['third_parties'] ?? false) ? now() : null,
            ...$state,
        ]);

        // Le persone fisiche hanno la residenza negli indirizzi (le sedi sono per le società).
        $client->addresses()->create([
            'address_type_id' => 1,
            'name' => 'Residenza',
            'street' => $residenza['street'] ?? null,
            'numero' => $residenza['numero'] ?? null,
            'zip_code' => $residenza['zip_code'] ?? null,
            'city' => $residenza['city'] ?? null,
            'province' => $residenza['province'] ?? null,
        ]);

        KycQuestionnaire::query()->create([
            ...$kyc,
            'client_id' => $client->getKey(),
            'pratica_id' => (string) $pratica->getKey(),
            'status' => KycStatus::Complete,
            'compiled_at' => now(),
        ]);

        $pratica->update([
            'nome_cliente' => $client->first_name,
            'cognome_cliente' => $client->name,
            'codice_fiscale' => $client->tax_code,
        ]);

        PraticaClient::query()->create([
            'pratica_id' => $pratica->getKey(),
            'client_id' => $client->getKey(),
            'role' => 'richiedente',
            'name' => trim($client->first_name.' '.$client->name),
        ]);

        $this->fase = 'cliente_salvato';
    }

    private function apriCliente(Pratica $pratica): void
    {
        $contract = SimulationContractType::from((string) $this->bozza['contract_type']);
        $relationship = self::relationshipFrom($this->bozza['employment_relationship']);
        $this->campiCliente = FinancingClientFields::forContract($contract);

        if ($relationship?->isRetiree()) {
            $this->campiCliente = array_values(array_diff($this->campiCliente, ['hiring_date']));
        }

        $this->cliente = array_filter([
            ...$this->datiLettiDaiDocumenti($pratica),
            'sex' => $this->bozza['sex'] ?? null,
            'hiring_date' => $this->bozza['hired_at'] ?? null,
            'salary_quote' => $this->bozza['assignable_quota'] ?? null,
        ], fn (mixed $value): bool => filled($value));
        $this->fase = 'cliente';
        $this->clienteForm->fill($this->cliente);
    }

    /**
     * Dati anagrafici letti dall'AI sui documenti d'identità e sul codice fiscale caricati: l'agente li conferma o li corregge.
     *
     * @return array<string, string>
     */
    private function datiLettiDaiDocumenti(Pratica $pratica): array
    {
        $letti = [];

        $documents = Document::query()
            ->where('documentable_type', 'pratica')
            ->where('documentable_id', (string) $pratica->getKey())
            ->whereHas('documentType', fn ($query) => $query->whereIn('ai_kind', ['identita', 'codice_fiscale']))
            ->with('documentType')
            ->get()
            ->sortBy(fn (Document $document): int => $document->documentType?->ai_kind === 'identita' ? 0 : 1);

        foreach ($documents as $document) {
            $fields = $document->analyses()->where('kind', 'extraction')->latest('id')->first()?->result['fields'] ?? null;

            if (! is_array($fields)) {
                continue;
            }

            foreach (['name' => 'surname', 'first_name' => 'name', 'tax_code' => 'fiscal_code'] as $client => $field) {
                $value = is_string($fields[$field] ?? null) ? trim($fields[$field]) : '';

                if ($value !== '' && ! isset($letti[$client])) {
                    $letti[$client] = $client === 'tax_code' ? strtoupper(str_replace(' ', '', $value)) : $value;
                }
            }
        }

        return $letti;
    }

    /**
     * @return list<string>
     */
    private function tipiMancanti(Pratica $pratica): array
    {
        $presenti = Document::query()
            ->where('documentable_type', 'pratica')
            ->where('documentable_id', (string) $pratica->getKey())
            ->whereNotNull('document_type_id')
            ->whereHas('media', fn ($query) => $query->where('collection_name', Document::COLLECTION))
            ->pluck('document_type_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $mancanti = [];

        foreach ($this->documentiRichiesti as $document) {
            if (! in_array($document['document_type_id'], $presenti, true)) {
                $mancanti[] = $document['name'];
            }
        }

        return $mancanti;
    }

    private function compilaDaCodiceFiscale(?string $state, Set $set): void
    {
        $decoded = app(CodiceFiscaleDecoder::class)->decodeVerified($state);

        if ($decoded === null) {
            if (strlen(trim((string) $state)) === 16) {
                Notification::make()
                    ->title('Codice fiscale non valido')
                    ->body('Il carattere di controllo non è corretto: data e luogo di nascita non sono stati compilati.')
                    ->warning()
                    ->send();
            }

            return;
        }

        $set('birth_date', $decoded['birth_date']->toDateString());
        $set('birth_place', $decoded['birth_place']);
        $set('sex', $decoded['sex']);
    }

    private function rememberQuote(FinancingQuote $quote, string $status, string $fase): void
    {
        $this->quoteId = $quote->getKey();
        $this->preventivoNumero = $quote->number;
        $this->codicePratica = $quote->pratica?->codice_pratica;
        $this->statoPratica = $status;
        $this->fase = $fase;
    }

    private function uploadedFile(mixed $value): ?UploadedFile
    {
        if ($value instanceof UploadedFile) {
            return $value;
        }

        if (is_array($value)) {
            $first = reset($value);

            return $this->uploadedFile($first === false ? null : $first);
        }

        if (is_string($value) && $value !== '' && Storage::disk('local')->exists($value)) {
            $path = Storage::disk('local')->path($value);

            return new UploadedFile($path, basename($path), null, null, true);
        }

        return null;
    }

    private function fornitore(): ?Fornitore
    {
        $user = Auth::user();

        return $user instanceof User ? $user->fornitore : null;
    }

    private static function contractTypeFrom(mixed $state): ?SimulationContractType
    {
        return $state instanceof SimulationContractType ? $state : SimulationContractType::tryFrom((string) $state);
    }

    private static function relationshipFrom(mixed $state): ?EmploymentRelationship
    {
        return $state instanceof EmploymentRelationship ? $state : EmploymentRelationship::tryFrom((string) $state);
    }
}
