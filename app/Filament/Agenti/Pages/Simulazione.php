<?php

namespace App\Filament\Agenti\Pages;

use App\Enums\EmploymentRelationship;
use App\Enums\SimulationContractType;
use App\Models\User;
use App\Services\Preventivazione\FinancingQuoteRecorder;
use App\Services\Preventivazione\PracticeFinalizer;
use App\Services\Preventivazione\QuotationService;
use App\Services\Preventivazione\QuoteUnavailable;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Unico\Core\Models\Fornitore;

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

    public ?string $errorePreventivo = null;

    public bool $preventivoDeciso = false;

    public ?int $preventivoNumero = null;

    public ?string $codicePratica = null;

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
        $this->preventivoDeciso = false;
        $this->preventivoNumero = null;
        $this->codicePratica = null;
        $this->esito = null;
        $this->errorePreventivo = null;

        try {
            $result = $quotation->quote($this->bozza);
        } catch (QuoteUnavailable $e) {
            $this->errorePreventivo = $e->getMessage();
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        $this->esito = ['min' => $result['min'], 'max' => $result['max']];
    }

    public function salvaPreventivo(FinancingQuoteRecorder $recorder): void
    {
        $fornitore = $this->fornitore();

        if ($this->bozza === null || $this->esito === null || $this->preventivoDeciso || $fornitore === null) {
            return;
        }

        $quote = $recorder->store($fornitore, $this->bozza, $this->esito, 'salvato', null, Auth::user());
        $this->preventivoNumero = $quote->number;
        $this->preventivoDeciso = true;
    }

    public function perfeziona(PracticeFinalizer $finalizer): void
    {
        $user = Auth::user();
        $fornitore = $this->fornitore();

        if ($this->bozza === null || $this->esito === null || $this->preventivoDeciso || ! $user instanceof User || $fornitore === null) {
            return;
        }

        try {
            $quote = $finalizer->finalize($fornitore, $this->bozza, $this->esito, $user);
        } catch (QuoteUnavailable $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        $this->preventivoNumero = $quote->number;
        $this->codicePratica = $quote->pratica?->codice_pratica;
        $this->preventivoDeciso = true;
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
