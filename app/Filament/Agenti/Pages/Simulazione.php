<?php

namespace App\Filament\Agenti\Pages;

use App\Enums\EmploymentRelationship;
use App\Enums\KycStatus;
use App\Enums\SimulationContractType;
use App\Filament\Resources\Clients\Schemas\ClientForm;
use App\Filament\Resources\Clients\Schemas\KycQuestionnaireForm;
use App\Models\FinancingQuote;
use App\Models\KycQuestionnaire;
use App\Models\User;
use App\Services\CodiceFiscaleDecoder;
use App\Services\Preventivazione\PracticeFinalizer;
use App\Services\Preventivazione\QuotationService;
use App\Services\Preventivazione\QuoteUnavailable;
use App\Support\FinancingClientFields;
use BackedEnum;
use Carbon\CarbonImmutable;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Url;
use Throwable;
use Unico\Core\Documents\IntakeResult;
use Unico\Core\Documents\PracticeDocumentIntake;
use Unico\Core\Documents\PracticeDocumentRequestMailer;
use Unico\Core\Documents\PracticeTaskDocuments;
use Unico\Core\Filament\Schemas\PracticeDocumentsUpload;
use Unico\Core\Filament\Schemas\PracticeSignaturePackageForm;
use Unico\Core\Models\Client;
use Unico\Core\Models\Cliente;
use Unico\Core\Models\Company;
use Unico\Core\Models\Document;
use Unico\Core\Models\DocumentType;
use Unico\Core\Models\Fornitore;
use Unico\Core\Models\Pratica;
use Unico\Core\Models\PraticaClient;
use Unico\Core\Models\TipoProdottoSub;
use Unico\Core\Pdf\PracticeModuleData;

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

    /** preventivo | informativa | contatti | perfezionare | cliente | firma */
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
     * Stato dell'elenco della modulistica da firmare.
     *
     * @var array<string, mixed>
     */
    public array $moduli = [];

    /**
     * Stato del campo di caricamento unico (componente di unico-core).
     *
     * @var array<string, mixed>
     */
    public array $documenti = [];

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

    /** Preventivo salvato da riprendere e perfezionare (dall'elenco «Preventivi da perfezionare»). */
    #[Url(as: 'preventivo')]
    public ?int $preventivoDaPerfezionare = null;

    public function mount(): void
    {
        $this->form->fill();

        if ($this->preventivoDaPerfezionare !== null) {
            $this->riprendiPreventivo($this->preventivoDaPerfezionare);
        }
    }

    /** Riporta nella pagina un preventivo salvato dell'agente, pronto da perfezionare; non più disponibile se è già in carico a un istruttore. */
    private function riprendiPreventivo(int $id): void
    {
        $quote = FinancingQuote::query()
            ->with('pratica')
            ->where('fornitore_id', $this->fornitore()?->getKey() ?? 0)
            ->where('status', 'salvato')
            ->find($id);

        if ($quote === null || $quote->pratica?->employee_id !== null) {
            Notification::make()->title('Preventivo non disponibile')->body('Non esiste, è già perfezionato oppure è in carico all\'istruttoria.')->warning()->send();

            return;
        }

        $contract = SimulationContractType::from($quote->contract_type);
        $this->bozza = [
            'contract_type' => $quote->contract_type,
            'sex' => $quote->sex,
            'birth_date' => $quote->birth_date?->toDateString(),
            'employment_relationship' => $quote->employment_relationship,
            'hired_at' => $quote->hired_at?->toDateString(),
            'starts_at' => $quote->starts_at?->toDateString(),
            'duration_months' => $quote->duration_months,
            'assignable_quota' => $quote->assignable_quota,
            'requested_amount' => $quote->requested_amount,
            'age_at_start' => $quote->age_at_start,
            'seniority_months' => $quote->seniority_months,
            'company_name' => $quote->company_name,
            'vat_number' => $quote->vat_number,
        ];
        $this->esito = $quote->disbursed_min !== null ? ['min' => (float) $quote->disbursed_min, 'max' => (float) $quote->disbursed_max] : null;
        $this->preventivatoreAssente = $this->esito === null;
        $this->profilo = [
            'tipo_contratto' => $contract->getLabel(),
            'cliente' => $quote->company_name ? $quote->company_name.' (P.IVA '.$quote->vat_number.')' : null,
            'tipo_rapporto' => self::relationshipFrom($quote->employment_relationship)?->getLabel(),
            'durata_etichetta' => $quote->duration_months.' mesi',
            'quota_cedibile' => $quote->assignable_quota !== null ? (float) $quote->assignable_quota : null,
            'importo_richiesto' => $quote->requested_amount !== null ? (float) $quote->requested_amount : null,
            'data_decorrenza' => $quote->starts_at?->format('d/m/Y'),
        ];
        $this->rememberQuote($quote, 'PREVENTIVO', 'preventivo');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make([
                    Step::make('Dati del cliente')
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
                            TextInput::make('ragione_sociale')
                                ->label('Ragione sociale')
                                ->maxLength(191)
                                ->visible(fn (Get $get): bool => self::isCorporate($get('tipo_contratto')))
                                ->required(fn (Get $get): bool => self::isCorporate($get('tipo_contratto'))),
                            TextInput::make('partita_iva')
                                ->label('Partita IVA')
                                ->regex('/^\d{11}$/')
                                ->validationMessages(['regex' => 'La partita IVA ha 11 cifre.'])
                                ->visible(fn (Get $get): bool => self::isCorporate($get('tipo_contratto')))
                                ->required(fn (Get $get): bool => self::isCorporate($get('tipo_contratto'))),
                            ToggleButtons::make('sesso')
                                ->label('Sesso')
                                ->options(['M' => 'M', 'F' => 'F'])
                                ->inline()
                                ->visible(fn (Get $get): bool => ! self::isCorporate($get('tipo_contratto')))
                                ->required(fn (Get $get): bool => ! self::isCorporate($get('tipo_contratto'))),
                            DatePicker::make('data_nascita')
                                ->label('Data di nascita')
                                ->native(false)
                                ->displayFormat('d/m/Y')
                                ->maxDate(today()->subYears(18))
                                ->visible(fn (Get $get): bool => ! self::isCorporate($get('tipo_contratto')))
                                ->required(fn (Get $get): bool => ! self::isCorporate($get('tipo_contratto'))),
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
                                ->visible(fn (Get $get): bool => ! self::isCorporate($get('tipo_contratto')))
                                ->required(fn (Get $get): bool => ! self::isCorporate($get('tipo_contratto')))
                                ->live(),
                            DatePicker::make('data_assunzione')
                                ->label('Data assunzione')
                                ->native(false)
                                ->displayFormat('d/m/Y')
                                ->maxDate(today())
                                ->after('data_nascita')
                                ->visible(fn (Get $get): bool => ! self::isCorporate($get('tipo_contratto')) && self::relationshipFrom($get('tipo_rapporto'))?->isRetiree() === false)
                                ->required(fn (Get $get): bool => ! self::isCorporate($get('tipo_contratto')) && self::relationshipFrom($get('tipo_rapporto'))?->isRetiree() === false),
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
        $relationship = self::relationshipFrom($state['tipo_rapporto'] ?? null);
        $decorrenza = CarbonImmutable::parse($state['data_decorrenza']);
        $assunzione = filled($state['data_assunzione'] ?? null) ? CarbonImmutable::parse($state['data_assunzione']) : null;
        $nascita = filled($state['data_nascita'] ?? null) ? CarbonImmutable::parse($state['data_nascita']) : null;

        $quota = $contract->asksQuotaCedibile() ? (float) $state['quota_cedibile'] : null;
        $importo = $contract->asksQuotaCedibile() ? null : (float) $state['importo_richiesto'];

        if ($contract->isCorporate()) {
            $this->simulaSocieta($contract, $state, $decorrenza, $importo);

            return;
        }

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
        $this->azzeraPercorso();
        $this->esito = null;
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

    /** Una nuova simulazione riparte dall'inizio: nessuna pratica, documento o dato del cliente della precedente. */
    private function azzeraPercorso(): void
    {
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
        $this->documenti = [];
        $this->moduli = [];
        $this->campiCliente = [];
        $this->cliente = [];
        $this->documentiMancanti = [];
        $this->errorePreventivo = null;
    }

    /** Leasing, chirografario e microcredito: per società, senza dati della persona e senza preventivatore. */
    private function simulaSocieta(SimulationContractType $contract, array $state, CarbonImmutable $decorrenza, ?float $importo): void
    {
        $this->bozza = [
            'contract_type' => $contract->value,
            'company_name' => trim((string) $state['ragione_sociale']),
            'vat_number' => preg_replace('/\s+/', '', (string) $state['partita_iva']),
            'starts_at' => $decorrenza->toDateString(),
            'duration_months' => $contract->durationInMonths((int) $state['durata']),
            'requested_amount' => $importo,
        ];
        $this->profilo = [
            'tipo_contratto' => $contract->getLabel(),
            'cliente' => $this->bozza['company_name'].' (P.IVA '.$this->bozza['vat_number'].')',
            'tipo_rapporto' => null,
            'durata_etichetta' => $contract->durationLabel((int) $state['durata']),
            'quota_cedibile' => null,
            'importo_richiesto' => $importo,
            'data_decorrenza' => $decorrenza->format('d/m/Y'),
        ];
        $this->azzeraPercorso();
        $this->esito = null;
        $this->preventivatoreAssente = true;
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
    public function perfeziona(PracticeTaskDocuments $documents): void
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

        $fields[] = Actions::make([
            Action::make('posticipaInformativa')
                ->label('Carica più tardi')
                ->color('gray')
                ->icon('heroicon-o-clock')
                ->action(fn () => $this->posticipaInformativa()),
        ]);

        return $schema->components($fields)->statePath('informativaAllegati');
    }

    public function caricaInformativa(PracticeDocumentIntake $intake): void
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

            $stored = $intake->storeTyped($pratica, $file, (int) $document['document_type_id'], DocumentType::query()->whereIn('id', $candidateIds)->get(), $user);

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

    /** L'informativa resta fra i documenti da caricare: si può consegnare più tardi con il caricamento unico. */
    public function posticipaInformativa(): void
    {
        if ($this->fase !== 'informativa') {
            return;
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

    public function confermaContatti(PracticeFinalizer $finalizer, PracticeTaskDocuments $documents): void
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
        // Task di stato più check list del prodotto (per gruppo di tipologia lavorativa); l'identità per prima.
        $this->documentiRichiesti = $documents->forProduct($productName, $this->gruppoLavorativo())
            ->map(fn ($requirement): array => [
                'document_type_id' => (int) $requirement->document_type_id,
                'name' => (string) $requirement->documentType?->name,
                'required' => (bool) $requirement->is_required,
            ])
            ->values()
            ->all();
        $this->rememberQuote($quote, 'PERFEZIONARE', 'perfezionare');
        $this->documentiMancanti = [];
        $this->documentiForm->fill();
    }

    /**
     * Elenco dei documenti richiesti con un solo campo di caricamento: ogni file viene riconosciuto (regex, poi AI),
     * letto, archiviato e i dati dell'identità compilano l'anagrafica del cliente (componente di unico-core).
     */
    public function documentiForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                ...PracticeDocumentsUpload::components(
                    fn (): ?Pratica => FinancingQuote::query()->find($this->quoteId)?->pratica,
                    fn (): Collection => $this->tipiAttesi(),
                    fn (array $results) => $this->dopoCaricamento($results),
                ),
                Actions::make([
                    Action::make('richiediAlCliente')
                        ->label('Chiedi i documenti al cliente per email')
                        ->icon('heroicon-o-envelope')
                        ->color('gray')
                        ->visible(fn (): bool => $this->documentiDaRichiedere()->isNotEmpty() && filled($this->contatti['email'] ?? null))
                        ->requiresConfirmation()
                        ->modalHeading('Invia la richiesta al cliente')
                        ->modalDescription(fn (): string => 'Invio un\'email a '.($this->contatti['email'] ?? '').', con te in copia ('.($this->emailAgente() ?? 'nessun indirizzo').'), con l\'elenco dei documenti ancora da caricare: il cliente risponde allegandoli e li acquisisco io. Nell\'oggetto c\'è il codice pratica.')
                        ->modalSubmitActionLabel('Invia')
                        ->action(fn () => $this->richiediAlCliente()),
                ]),
            ])
            ->statePath('documenti');
    }

    /**
     * Invia al cliente (e in copia all'agente) la richiesta dei documenti mancanti dall'account email dell'app.
     * Le risposte con gli allegati si acquisiscono da sole (unico-core: lettura della casella e acquisizione).
     */
    public function richiediAlCliente(): void
    {
        $pratica = FinancingQuote::query()->find($this->quoteId)?->pratica;
        $types = $this->documentiDaRichiedere();
        $email = (string) ($this->contatti['email'] ?? '');

        if ($pratica === null || $types->isEmpty() || $email === '') {
            return;
        }

        $nome = trim(($pratica->nome_cliente ?? '').' '.($pratica->cognome_cliente ?? '')) ?: 'cliente';

        try {
            app(PracticeDocumentRequestMailer::class)->send(
                $pratica,
                $types,
                $email,
                $nome,
                $this->emailAgente(),
                $this->fornitore()?->name,
                Auth::user(),
            );
        } catch (Throwable $e) {
            report($e);
            Notification::make()->title('Email non inviata')->body('Non sono riuscito a inviare l\'email: riprova più tardi.')->danger()->send();

            return;
        }

        Notification::make()->title('Richiesta inviata')->body('Ho scritto a '.$email.' con te in copia: alla risposta con gli allegati i documenti si aggiornano.')->success()->send();
    }

    /**
     * Documenti attesi non ancora ricevuti (informativa compresa).
     *
     * @return Collection<int, DocumentType>
     */
    private function documentiDaRichiedere(): Collection
    {
        $pratica = FinancingQuote::query()->find($this->quoteId)?->pratica;
        $types = $this->tipiAttesi();

        if ($pratica === null || $types->isEmpty()) {
            return collect();
        }

        $received = app(PracticeDocumentIntake::class)->received($pratica, $types);

        return $types->reject(fn (DocumentType $type): bool => $received[$type->getKey()] ?? false)->values();
    }

    private function emailAgente(): ?string
    {
        return $this->fornitore()?->email ?: (Auth::user() instanceof User ? Auth::user()->email : null);
    }

    /**
     * @param  list<IntakeResult>  $results
     */
    private function dopoCaricamento(array $results): void
    {
        foreach ($results as $result) {
            $this->esitiDocumenti[] = [
                'file' => $result->fileName,
                'tipo' => $result->type?->name,
            ];
        }

        $pratica = FinancingQuote::query()->find($this->quoteId)?->pratica;

        if ($pratica === null) {
            return;
        }

        $this->documentiMancanti = $this->tipiMancanti($pratica);

        if ($this->documentiMancanti === [] && $this->fase === 'perfezionare') {
            $this->apriCliente($pratica);
        }
    }

    /** Gruppo documentale della tipologia lavorativa scelta (pensionati, privati, pubblici, parapubblici, autonomi). */
    private function gruppoLavorativo(): ?string
    {
        $relationship = self::relationshipFrom($this->bozza['employment_relationship'] ?? null);

        if ($relationship === null) {
            return null;
        }

        $group = TipoProdottoSub::query()->where('name', $relationship->getLabel())->value('employment_document_group');

        return $group instanceof BackedEnum ? $group->value : ($group === null ? null : (string) $group);
    }

    /**
     * Documenti attesi: informativa (se non ancora consegnata) e quelli del task del prodotto.
     *
     * @return Collection<int, DocumentType>
     */
    private function tipiAttesi(): Collection
    {
        $ids = collect([...array_column($this->informativaRichiesta, 'document_type_id'), ...array_column($this->documentiRichiesti, 'document_type_id')])->unique()->values();
        $types = DocumentType::query()->whereIn('id', $ids)->get()->keyBy('id');

        return $ids->map(fn (int $id): ?DocumentType => $types->get($id))->filter()->values();
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
                ->createOptionForm(ClientForm::datoreDiLavoroForm())
                ->createOptionUsing(function (array $data): int {
                    $quote = FinancingQuote::query()->find($this->quoteId);
                    $tipo = self::relationshipFrom($quote?->employment_relationship)?->getLabel();

                    // Il datore eredita il tipo di amministrazione del preventivo (statale, pubblico, privato SPA...).
                    return (int) Client::query()->create([
                        'name' => $data['name'],
                        'tax_code' => filled($data['tax_code'] ?? null) ? strtoupper(trim((string) $data['tax_code'])) : null,
                        'is_person' => false,
                        'company_id' => $quote?->pratica?->company_id,
                        'employment_typology' => $tipo,
                    ])->getKey();
                }),
            'hiring_date' => DatePicker::make('hiring_date')->label('Data di assunzione')->native(false)->displayFormat('d/m/Y'),
            'salary' => TextInput::make('salary')->label('Reddito netto mensile')->numeric()->prefix('€'),
            'salary_quote' => TextInput::make('salary_quote')->label('Quota cedibile')->numeric()->prefix('€'),
        ];

        $corporate = SimulationContractType::tryFrom((string) ($this->bozza['contract_type'] ?? ''))?->isCorporate() === true;
        $isPerson = fn (?Get $get = null): bool => ! $corporate;

        if ($corporate) {
            $fields['name'] = TextInput::make('name')->label('Ragione sociale')->required()->maxLength(191);
            $fields['vat_number'] = TextInput::make('vat_number')->label('Partita IVA')->required()->regex('/^\d{11}$/')
                ->validationMessages(['regex' => 'La partita IVA ha 11 cifre.']);
        }

        $visible = array_values(array_intersect(array_keys($fields), $this->campiCliente));

        return $schema
            ->components([
                ...array_map(fn (string $name) => $fields[$name], $visible),
                Section::make($corporate ? 'Sede legale' : 'Residenza')
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
                        Section::make('Persona giuridica')
                            ->columns(2)
                            ->visible($corporate)
                            ->schema(KycQuestionnaireForm::companyFields(true)),
                    ]),
                Section::make('Consensi privacy del cliente')
                    ->visible(! $corporate)
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

        if (SimulationContractType::tryFrom((string) ($this->bozza['contract_type'] ?? ''))?->isCorporate()) {
            $this->salvaSocieta($state);

            return;
        }

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

        $attributes = [
            'company_id' => $pratica->company_id,
            'is_person' => true,
            'email' => $this->contatti['email'] ?? null,
            'phone' => $this->contatti['phone'] ?? null,
            'consent_marketing_at' => ($consensi['marketing'] ?? false) ? now() : null,
            'consent_group_at' => ($consensi['group'] ?? false) ? now() : null,
            'consent_profiling_at' => ($consensi['profiling'] ?? false) ? now() : null,
            'consent_third_parties_at' => ($consensi['third_parties'] ?? false) ? now() : null,
            ...$state,
        ];

        // I documenti d'identità caricati hanno già creato il cliente: si completa, altrimenti si crea.
        $client = app(PracticeModuleData::class)->findClient($pratica);

        if ($client === null) {
            $client = Client::query()->create($attributes);
        } else {
            $client->update($attributes);
        }

        // Le persone fisiche hanno la residenza negli indirizzi (le sedi sono per le società).
        $client->addresses()->updateOrCreate(['address_type_id' => 1], [
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

        PraticaClient::query()->firstOrCreate(
            ['pratica_id' => $pratica->getKey(), 'client_id' => $client->getKey()],
            ['role' => 'richiedente', 'name' => trim($client->first_name.' '.$client->name)],
        );

        $this->apriFirma($pratica);
    }

    /**
     * Cliente società: anagrafica con ragione sociale e partita IVA, sede legale, KYC. I documenti d'identità caricati
     * sono dell'amministratore, già collegato alla pratica con quel ruolo, e diventa il legale rappresentante.
     *
     * @param  array<string, mixed>  $state
     */
    private function salvaSocieta(array $state): void
    {
        $pratica = FinancingQuote::query()->find($this->quoteId)?->pratica;

        if ($pratica === null) {
            return;
        }

        $residenza = Arr::pull($state, 'residenza', []);
        $kyc = Arr::pull($state, 'kyc', []);
        Arr::pull($state, 'consensi');

        $attributes = [
            'company_id' => $pratica->company_id,
            'is_person' => false,
            'name' => $state['name'],
            'vat_number' => $state['vat_number'],
            'email' => $this->contatti['email'] ?? null,
            'phone' => $this->contatti['phone'] ?? null,
        ];

        $client = Client::query()->where('vat_number', $state['vat_number'])->first();
        $client === null ? $client = Client::query()->create($attributes) : $client->update($attributes);

        $companyId = $pratica->company_id ?? Company::query()->value('id');

        if ($companyId !== null) {
            $client->branches()->updateOrCreate(['is_main_office' => true], [
                'company_id' => $companyId,
                'name' => 'Sede legale',
                'address' => $residenza['street'] ?? null,
                'street_number' => $residenza['numero'] ?? null,
                'zip_code' => $residenza['zip_code'] ?? null,
                'city' => $residenza['city'] ?? null,
                'province' => $residenza['province'] ?? null,
            ]);
        }

        KycQuestionnaire::query()->create([
            ...$kyc,
            'client_id' => $client->getKey(),
            'pratica_id' => (string) $pratica->getKey(),
            'status' => KycStatus::Complete,
            'compiled_at' => now(),
        ]);

        $administratorId = PraticaClient::query()->where('pratica_id', $pratica->getKey())->where('role', 'amministratore')->value('client_id');

        if ($administratorId !== null && blank($client->legal_representative_id)) {
            $client->update(['legal_representative_id' => $administratorId]);
        }

        $pratica->update(['cognome_cliente' => $client->name, 'nome_cliente' => null, 'codice_fiscale' => $client->vat_number]);

        PraticaClient::query()->firstOrCreate(
            ['pratica_id' => $pratica->getKey(), 'client_id' => $client->getKey()],
            ['role' => 'richiedente', 'name' => $client->name],
        );

        $this->apriFirma($pratica);
    }

    /**
     * Cliente e KYC compilati: la pratica passa alla fase della modulistica, da stampare e far firmare al cliente con OTP.
     */
    private function apriFirma(Pratica $pratica): void
    {
        $quote = FinancingQuote::query()->find($this->quoteId);
        $productName = SimulationContractType::from((string) $quote?->contract_type)->productName();

        app(PracticeFinalizer::class)->advance($pratica, $productName, 'FASCICOLO COMPLETO');

        $this->statoPratica = 'FASCICOLO COMPLETO';
        $this->fase = 'firma';
        $this->moduliForm->fill(['banca' => $pratica->clienti_id]);
    }

    /**
     * Moduli da stampare e firmare per la pratica: quelli del prodotto, più i documenti specifici della banca scelta
     * (es. l'informativa Compass solo se la banca è Compass).
     *
     * @return Collection<int, DocumentType>
     */
    private function tipiModulistica(): Collection
    {
        $quote = FinancingQuote::query()->find($this->quoteId);
        $pratica = $quote?->pratica;

        if ($pratica === null) {
            return collect();
        }

        $productName = SimulationContractType::from((string) $quote->contract_type)->productName();
        $ids = app(PracticeTaskDocuments::class)->forProductStatus($productName, 'FASCICOLO COMPLETO', $pratica->clienti_id)
            ->pluck('document_type_id');

        return DocumentType::query()->whereIn('id', $ids)->with('pdfModule')->get()->sortBy(fn (DocumentType $type): int => (int) $ids->search($type->getKey()))->values();
    }

    /** La banca della pratica: decide quali documenti specifici di un istituto vanno stampati. */
    private function scegliBanca(mixed $bancaId): void
    {
        $pratica = FinancingQuote::query()->find($this->quoteId)?->pratica;

        if ($pratica === null) {
            return;
        }

        $banca = filled($bancaId) ? Cliente::query()->find($bancaId) : null;

        $pratica->update([
            'clienti_id' => $banca?->getKey(),
            'denominazione_banca' => $banca?->name,
            'abi' => $banca?->abi,
        ]);
    }

    /** Modulistica da firmare: elenco, dati mancanti e invio per la firma OTP (componente di unico-core). */
    public function moduliForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('banca')
                    ->label('Banca / istituto')
                    ->helperText('Decide quali documenti specifici di una banca vanno stampati (ad esempio l\'informativa Compass).')
                    ->options(fn (): array => Cliente::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->live()
                    ->dehydrated(false)
                    ->afterStateUpdated(fn (mixed $state) => $this->scegliBanca($state)),
                ...PracticeSignaturePackageForm::components(
                    fn (): ?Pratica => FinancingQuote::query()->find($this->quoteId)?->pratica,
                    fn (): Collection => $this->tipiModulistica(),
                ),
            ])
            ->statePath('moduli');
    }

    private function apriCliente(Pratica $pratica): void
    {
        $contract = SimulationContractType::from((string) $this->bozza['contract_type']);

        if ($contract->isCorporate()) {
            $this->campiCliente = ['name', 'vat_number'];
            $this->cliente = ['name' => $this->bozza['company_name'], 'vat_number' => $this->bozza['vat_number']];
            $this->fase = 'cliente';
            $this->clienteForm->fill($this->cliente);

            return;
        }

        $relationship = self::relationshipFrom($this->bozza['employment_relationship']);
        $this->campiCliente = FinancingClientFields::forContract($contract);

        if ($relationship?->isRetiree()) {
            $this->campiCliente = array_values(array_diff($this->campiCliente, ['hiring_date']));
        }

        $client = app(PracticeModuleData::class)->findClient($pratica);

        $this->cliente = array_filter([
            'first_name' => $client?->first_name,
            'name' => $client?->name,
            'tax_code' => $client?->tax_code,
            'birth_date' => $client?->birth_date?->toDateString(),
            'birth_place' => $client?->birth_place,
            'sex' => $client?->sex ?? $this->bozza['sex'] ?? null,
            'hiring_date' => $this->bozza['hired_at'] ?? null,
            'salary_quote' => $this->bozza['assignable_quota'] ?? null,
        ], fn (mixed $value): bool => filled($value));
        $this->fase = 'cliente';
        $this->clienteForm->fill($this->cliente);
    }

    /**
     * Documenti d'identità obbligatori non ancora ricevuti: servono a compilare l'anagrafica, quindi finché mancano non si
     * passa al cliente. Gli altri documenti della check list si possono caricare anche dopo (o chiedere al cliente per email).
     *
     * @return list<string>
     */
    private function tipiMancanti(Pratica $pratica): array
    {
        $required = collect($this->documentiRichiesti)->where('required', true);
        $types = DocumentType::query()
            ->whereIn('id', $required->pluck('document_type_id'))
            ->whereNotNull('ai_kind')
            ->whereIn('ai_kind', ['identita', 'codice_fiscale'])
            ->get();
        $received = app(PracticeDocumentIntake::class)->received($pratica, $types);

        return $types
            ->reject(fn (DocumentType $type): bool => $received[$type->getKey()] ?? false)
            ->pluck('name')
            ->values()
            ->all();
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

    private static function isCorporate(mixed $state): bool
    {
        return self::contractTypeFrom($state)?->isCorporate() === true;
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
