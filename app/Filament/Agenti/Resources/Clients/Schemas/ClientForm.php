<?php

namespace App\Filament\Agenti\Resources\Clients\Schemas;

use App\Filament\Agenti\Resources\Pratiche\Schemas\ClienteApiActions;
use App\Filament\Resources\Clients\Schemas\ClientForm as AdminClientForm;
use App\Filament\Resources\Clients\Schemas\KycQuestionnaireForm;
use App\Models\Client;
use App\Services\CodiceFiscaleDecoder;
use App\Services\Istruttoria\DatoreDiLavoro;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Builder;
use Unico\Core\Enums\IbanHolder;
use Unico\Core\Enums\MaritalStatus;
use Unico\Core\Enums\PaymentMethod;

/**
 * Anagrafica, valutazione AML e KYC del richiedente: la stessa scheda nel form della pratica (relazione `richiedente`)
 * e nella scheda cliente. I percorsi relativi (`../is_person`) valgono in entrambi perché i campi stanno allo stesso livello.
 */
class ClientForm
{
    /**
     * @param  Closure(?Get): bool  $isPerson
     * @param  bool  $withScreening  azione di verifica PEP (usa la pratica aperta: solo nel form della pratica)
     * @return array<int, mixed>
     */
    public static function components(Closure $isPerson, bool $withScreening = true): array
    {
        // Persona fisica: l'interruttore segue il cognome; società: segue la partita IVA.
        $interruttorePersona = fn (bool $dopoIlCognome): Toggle => Toggle::make('is_person')
            ->label('Persona fisica')
            ->live()
            ->visible(fn (Get $get): bool => (bool) $get('is_person') === $dopoIlCognome);

        return [
            Grid::make(['default' => 1])
                ->columnSpanFull()
                ->schema([
                    Section::make('Anagrafica')
                        ->columns(['default' => 1, 'md' => 3])
                        ->schema([
                            TextInput::make('name')->label('Cognome / ragione sociale')->required()->maxLength(255),
                            $interruttorePersona(true),
                            TextInput::make('first_name')->label('Nome')->maxLength(255)->visible(fn (Get $get): bool => (bool) $get('is_person')),
                            TextInput::make('tax_code')
                                ->label('Codice fiscale')
                                ->maxLength(16)
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                                    if (! $get('is_person') || strlen(trim((string) $state)) !== 16) {
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
                                }),
                            $interruttorePersona(false),
                        ]),
                    Section::make('Dati anagrafici')
                        ->collapsed()
                        ->columns(['default' => 1, 'md' => 4])
                        ->visible(fn (Get $get): bool => (bool) $get('is_person'))
                        ->schema([
                            DatePicker::make('birth_date')->label('Data di nascita')->native(false)->displayFormat('d/m/Y'),
                            TextInput::make('birth_place')->label('Luogo di nascita')->maxLength(100),
                            Select::make('sex')->label('Sesso')->options(['M' => 'M', 'F' => 'F']),
                            TextInput::make('citizenship')->label('Cittadinanza')->maxLength(60),
                            Select::make('marital_status')->label('Stato civile')->options(MaritalStatus::class),
                            TextInput::make('family_members')->label('Numero familiari')->numeric()->integer()->minValue(0)->maxValue(30),
                            TextInput::make('family_members_with_income')->label('Familiari con reddito')->numeric()->integer()->minValue(0)->maxValue(30),
                        ]),
                ]),
            Section::make('Lavoro e reddito')
                ->columns(['default' => 1, 'md' => 2, 'xl' => 3])
                ->columnSpanFull()
                ->schema([
                    TextInput::make('salary')->label('Reddito')->numeric()->prefix('€'),
                    // Cessione e delega: il datore di lavoro eredita il tipo di amministrazione della pratica.
                    Select::make('employer_id')
                        ->label('Datore di lavoro')
                        ->options(fn (): array => Client::query()->where('is_person', false)->orderBy('name')->limit(200)->pluck('name', 'id')->all())
                        ->getSearchResultsUsing(fn (string $search): array => Client::query()->where('is_person', false)->where('name', 'like', "%{$search}%")->limit(50)->pluck('name', 'id')->all())
                        ->searchable()
                        ->createOptionForm(AdminClientForm::datoreDiLavoroForm())
                        ->createOptionUsing(fn (array $data, ?Client $record): int => (int) app(DatoreDiLavoro::class)->crea($record, $data)->getKey())
                        ->visible(fn (?Client $record): bool => app(DatoreDiLavoro::class)->richiesto($record)),
                    Select::make('occupation_type_id')->label('Tipo occupazione')
                        ->relationship('occupationType', 'name', fn (Builder $query) => $query->orderBy('sort_order'))
                        ->searchable()->preload(),
                    Select::make('hiring_type_id')->label('Tipo assunzione')
                        ->relationship('hiringType', 'name', fn (Builder $query) => $query->orderBy('sort_order'))
                        ->preload(),
                    Select::make('income_type_id')->label('Tipo reddito')
                        ->relationship('incomeType', 'name', fn (Builder $query) => $query->orderBy('sort_order'))
                        ->preload(),
                    TextInput::make('salary_months')->label('Numero mensilità')->numeric()->integer()->minValue(1)->maxValue(24),
                    TextInput::make('salary_day')->label('Giorno ricezione stipendio')->numeric()->integer()->minValue(1)->maxValue(31),
                    Select::make('payment_method')->label('Modalità di pagamento')->options(PaymentMethod::class),
                    Select::make('iban_holder')->label('Intestatario conto corrente')->options(IbanHolder::class),
                    Select::make('preferred_bank_id')
                        ->label('Banca preferita nuovi finanziamenti')
                        ->relationship('preferredBank', 'name', fn (Builder $query) => $query->where('is_active', true)->orderBy('name'))
                        ->searchable()
                        ->preload(),
                ]),
            Section::make('Valutazione rischio (AML)')
                ->key('valutazioneAml')
                ->collapsed()
                ->columns(['default' => 1, 'md' => 2, 'xl' => 4])
                ->columnSpanFull()
                ->headerActions([
                    ClienteApiActions::verificaPep(),
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
                ->relationship('questionarioKyc')
                ->collapsed()
                ->columns(['default' => 1, 'md' => 2, 'xl' => 3])
                ->columnSpanFull()
                ->schema([
                    KycQuestionnaireForm::financingPurpose($isPerson),
                    KycQuestionnaireForm::riskLevel(),
                    KycQuestionnaireForm::pepStatus($isPerson),
                    Textarea::make('notes')->label('Note')->columnSpanFull(),
                    KycQuestionnaireForm::personSection($isPerson),
                    Section::make('Persona giuridica')
                        ->columns(2)
                        ->visible(fn (?Get $get = null): bool => ! ($get instanceof Get && (bool) $get('../is_person')))
                        ->schema(KycQuestionnaireForm::companyFields()),
                ]),

        ];
    }
}
