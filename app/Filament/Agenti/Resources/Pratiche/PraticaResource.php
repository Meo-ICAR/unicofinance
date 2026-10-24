<?php

namespace App\Filament\Agenti\Resources\Pratiche;

use App\Filament\Actions\ExcelExportAction;
use App\Filament\Agenti\Actions\RinunciaClienteAction;
use App\Filament\Agenti\Resources\Clients\Schemas\ClientForm;
use App\Filament\Agenti\Resources\Pratiche\Pages\EditPratica;
use App\Filament\Agenti\Resources\Pratiche\Pages\ListPratiche;
use App\Filament\Agenti\Resources\Pratiche\Schemas\ClienteApiActions;
use App\Filament\RelationManagers\AddressesRelationManager;
use App\Filament\RelationManagers\BranchesRelationManager;
use App\Filament\RelationManagers\CointestatariGarantiRelationManager;
use App\Filament\RelationManagers\ImmobileRelationManager;
use App\Filament\RelationManagers\PraticheRelationManager;
use App\Filament\RelationManagers\ProvvigioniRelationManager;
use App\Filament\RelationManagers\WebsitesRelationManager;
use App\Filament\Traits\HasPlanAccess;
use App\Models\Pratica;
use App\Models\Provvigione;
use App\Services\Agenti\AgentImpersonationService;
use App\Services\CodiceFiscaleDecoder;
use App\Services\Istruttoria\PresaInCarico;
use App\Services\PortaleBanca;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Unico\Core\Filament\RelationManagers\DocumentsRelationManager;
use Unico\Core\Models\Cliente;
use Unico\Core\Models\Fornitore;
use Unico\Core\Models\TipoProdotto;

class PraticaResource extends Resource
{
    use HasPlanAccess;

    protected static ?string $model = Pratica::class;

    protected static ?string $slug = 'pratiche';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Pratiche';

    protected static ?string $modelLabel = 'Pratica';

    protected static ?string $pluralModelLabel = 'Pratiche';

    protected static ?string $recordTitleAttribute = 'codice_pratica';

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false;
    }

    /** Da «PRESA IN CARICO» in poi la pratica non si modifica più dall'agente (`PresaInCarico::modificabileDalProduttore`). */
    public static function canEdit(Model $record): bool
    {
        return app(PresaInCarico::class)->modificabileDalProduttore($record) && static::hasPlanFeatureAccess(write: true);
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $fornitoreId = app(AgentImpersonationService::class)->agentFornitoreId();
        $query = parent::getEloquentQuery();

        $fornitore = $fornitoreId === null ? null : Fornitore::query()->find($fornitoreId);

        if (! $fornitore instanceof Fornitore) {
            return $query->whereRaw('0 = 1');
        }

        $codiciConProvvigione = Provvigione::query()
            ->dellAgente($fornitore)
            ->whereNotNull('id_pratica')
            ->distinct()
            ->pluck('id_pratica');

        return $query->where(function (Builder $visibili) use ($fornitore, $codiciConProvvigione): void {
            $visibili->whereIn('codice_pratica', $codiciConProvvigione)
                ->orWhere(fn (Builder $terzi) => $terzi->where('is_notowned', true)->whereIn(
                    'codice_fiscale',
                    Pratica::query()
                        ->where('fornitori_id', $fornitore->getKey())
                        ->where('is_notowned', false)
                        ->whereNotNull('codice_fiscale')
                        ->where('codice_fiscale', '!=', '')
                        ->select('codice_fiscale'),
                ));
        });
    }

    /** Nei titoli e nelle briciole di pane la pratica è il cognome o la ragione sociale del richiedente, non il codice. */
    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        return $record?->cognome_cliente ?: $record?->codice_pratica;
    }

    public static function form(Schema $schema): Schema
    {
        $isPerson = fn (?Get $get = null): bool => $get instanceof Get && (bool) $get('../is_person');

        // Cessione e delega si calcolano sulla rata; gli altri prodotti su importo richiesto e durata.
        $sulla_rata = fn (Get $get): bool => in_array(TipoProdotto::query()->whereKey($get('tipoprodotto_id'))->value('name'), ['Cessione', 'Delega'], true);

        // Impegno di terzi: di un'altra società, ci interessano solo tipo, banca, scadenza e rata.
        $impegnoDiTerzi = fn (Get $get): bool => (bool) $get('is_notowned');

        return $schema->components([
            Section::make()
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 3])
                ->schema([
                    Placeholder::make('intestazione_prodotto')
                        ->label('Prodotto')
                        ->content(fn (Get $get): string => filled($get('denominazione_prodotto')) ? (string) $get('denominazione_prodotto') : (TipoProdotto::query()->whereKey($get('tipoprodotto_id'))->value('name') ?? '—')),
                    Placeholder::make('intestazione_banca')
                        ->label('Banca')
                        ->content(function (Get $get): HtmlString|string {
                            $nome = Cliente::query()->whereKey($get('clienti_id'))->value('name');
                            $url = static::portaleBanca($get('clienti_id'));

                            if ($nome === null) {
                                return '—';
                            }

                            return $url === null ? $nome : new HtmlString('<a href="'.e($url).'" target="_blank" rel="noopener" class="text-primary-600 underline">'.e($nome).' ↗</a>');
                        }),
                    Placeholder::make('intestazione_estremi')
                        ->label('Estremi richiedente')
                        ->content(fn (Get $get): string => collect([trim(($get('cognome_cliente') ?? '').' '.($get('nome_cliente') ?? '')), $get('codice_fiscale')])->filter()->implode(' · ') ?: '—'),
                ]),
            Section::make('Estremi richiedente')
                ->icon('heroicon-o-user')
                ->collapsed()
                ->columns(['default' => 1, 'md' => 3])
                ->columnSpanFull()
                ->schema([
                    TextInput::make('cognome_cliente')->label('Cognome')->maxLength(191),
                    TextInput::make('nome_cliente')->label('Nome')->maxLength(191),
                    TextInput::make('codice_fiscale')
                        ->label('Codice fiscale / P.IVA')
                        ->maxLength(16)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (?string $state, Set $set, ?Pratica $record): void {
                            if ($record?->richiedente === null || strlen(trim((string) $state)) !== 16) {
                                return;
                            }

                            $decoded = app(CodiceFiscaleDecoder::class)->decodeVerified($state);

                            if ($decoded === null) {
                                Notification::make()->title('Codice fiscale non valido')->body('Il carattere di controllo non è corretto: dati anagrafici non compilati.')->warning()->send();

                                return;
                            }

                            $set('richiedente.birth_date', $decoded['birth_date']->format('Y-m-d'));
                            $set('richiedente.sex', $decoded['sex']);
                            $set('richiedente.birth_place', $decoded['birth_place']);
                        })
                        ->suffixActions([
                            ClienteApiActions::schedaCliente(),
                            ClienteApiActions::cerved(),
                        ]),
                    TextInput::make('cliente_cellulare')
                        ->label('Cellulare')
                        ->tel()
                        ->required()
                        ->maxLength(50)
                        ->dehydrated(false),
                    TextInput::make('cliente_email')
                        ->label('Email')
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->columnSpan(2)
                        ->dehydrated(false),
                    TextInput::make('cliente_iban')
                        ->label('IBAN')
                        ->maxLength(34)
                        ->columnSpan(2)
                        ->dehydrated(false)
                        ->dehydrateStateUsing(fn (?string $state): ?string => filled($state)
                            ? strtoupper(str_replace(' ', '', $state))
                            : null)
                        ->suffixActions([
                            ClienteApiActions::openapi(),
                        ]),
                ]),
            Section::make('Prodotto richiesto')
                ->icon('heroicon-o-banknotes')
                ->collapsed()
                ->columns(['default' => 2, 'md' => 4])
                ->columnSpanFull()
                ->schema([
                    Select::make('tipoprodotto_id')
                        ->label('Tipo prodotto')
                        ->relationship('tipoprodotto', 'name')
                        ->searchable()
                        ->preload()
                        ->live(),
                    Toggle::make('is_notowned')->label('Impegno di terzi')->inline(false)->live(),
                    TextInput::make('rata')
                        ->label('Rata')
                        ->numeric()
                        ->prefix('€'),
                    TextInput::make('nrate')
                        ->label('Durata (mesi)')
                        ->numeric()
                        ->integer()
                        ->hidden($impegnoDiTerzi),
                    DatePicker::make('data_inserimento_pratica')
                        ->label('Data richiesta')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->hidden($impegnoDiTerzi),
                    DatePicker::make('scadenza')
                        ->label('Scadenza')
                        ->helperText('Fine del finanziamento, dell\'assegno di mantenimento o della ritenuta.')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->visible($impegnoDiTerzi),
                    TextInput::make('codice_pratica')->label('Numero pratica')->maxLength(255),
                    Select::make('clienti_id')
                        ->label('Banca')
                        ->options(fn (): array => Cliente::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->live()
                        ->suffixAction(
                            Action::make('portaleBanca')
                                ->label('Portale della banca')
                                ->tooltip('Apri il portale della banca per inserire la pratica')
                                ->icon('heroicon-m-arrow-top-right-on-square')
                                ->url(fn (Get $get): ?string => static::portaleBanca($get('clienti_id')), shouldOpenInNewTab: true)
                                ->visible(fn (Get $get): bool => static::portaleBanca($get('clienti_id')) !== null),
                        ),
                    TextInput::make('codice_pratica_banca')->label('Codice pratica banca')->helperText('Assegnato dalla banca sul suo portale')->maxLength(100)->hidden($impegnoDiTerzi),
                    TextInput::make('campagna_lead')->label('Campagna lead')->maxLength(255),
                    TextInput::make('amount')
                        ->label('Importo richiesto')
                        ->numeric()
                        ->prefix('€')
                        ->visible(fn (Get $get): bool => ! $sulla_rata($get) && ! $impegnoDiTerzi($get)),
                    TextInput::make('loan_purpose')->label('Finalità')->maxLength(255)->hidden($impegnoDiTerzi),
                ]),
            Tabs::make('Altri dati')
                ->columnSpanFull()
                ->tabs([
                    Tab::make('Anagrafica e KYC')
                        ->icon('heroicon-o-identification')
                        ->schema([
                            Section::make('Anagrafica richiedente')
                                ->description('Dati del cliente collegato dal codice fiscale.')
                                ->relationship('richiedente')
                                ->visible(fn (?Pratica $record): bool => $record?->richiedente !== null)
                                ->columns(2)
                                ->schema(ClientForm::components($isPerson)),
                        ]),
                    Tab::make('Dettagli')
                        ->icon('heroicon-o-clipboard-document-list')
                        ->schema([
                            Section::make('Istruttoria')
                                ->columnSpanFull()
                                ->columns(['default' => 1, 'md' => 3])
                                ->schema([
                                    Select::make('pratica_stato_id')
                                        ->label('Stato')
                                        ->relationship('praticaStato', 'codice')
                                        ->searchable()
                                        ->preload(),
                                    TextInput::make('denominazione_prodotto')->label('Prodotto')->maxLength(191),
                                    TextInput::make('denominazione_agente')->label('Agente')->maxLength(191),
                                ]),
                            Section::make('Avanzamento')
                                ->collapsed()
                                ->columnSpanFull()
                                ->columns(['default' => 4])
                                ->schema([
                                    DatePicker::make('data_inserimento_pratica')->label('Data inserimento')->native(false)->displayFormat('d/m/Y')->disabled(),
                                    DatePicker::make('presa_in_carico_at')->label('Data presa in carico')->native(false)->displayFormat('d/m/Y'),
                                    DatePicker::make('lavorazione_at')->label('Data lavorazione')->native(false)->displayFormat('d/m/Y'),
                                    DatePicker::make('sended_at')->label('Data invio istruttoria')->native(false)->displayFormat('d/m/Y'),
                                    DatePicker::make('approved_at')->label('Approvazione')->native(false)->displayFormat('d/m/Y'),
                                    DatePicker::make('erogated_at')->label('Erogazione')->native(false)->displayFormat('d/m/Y'),
                                    DatePicker::make('rejected_at')->label('Rifiuto')->native(false)->displayFormat('d/m/Y'),
                                ]),
                            Section::make('Importi')
                                ->columns(['default' => 1, 'md' => 2])
                                ->columnSpanFull()
                                ->schema([
                                    TextInput::make('net')->label('Importo netto')->numeric()->prefix('€'),
                                    TextInput::make('erogato')->label('Erogato')->numeric()->prefix('€'),
                                ]),
                        ]),
                ]),
        ]);
    }

    /** Portale della banca per l'immissione della pratica (indirizzo dedicato, altrimenti il sito dell'istituto). */
    public static function portaleBanca(mixed $clientiId): ?string
    {
        return PortaleBanca::url($clientiId);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->headerActions([ExcelExportAction::make()->sumColumns(['Importo'])])
            ->defaultSort('data_inserimento_pratica', 'desc')
            ->columns([
                TextColumn::make('codice_pratica')->label('Codice')->searchable()->sortable(),
                TextColumn::make('cognome_cliente')->label('Cognome')->searchable()->sortable(),
                TextColumn::make('nome_cliente')->label('Nome')->searchable(),
                TextColumn::make('tipoprodotto.name')->label('Tipo prodotto')->sortable(),
                TextColumn::make('praticaStato.codice')->label('Stato')->badge(),
                TextColumn::make('employee.name')->label('In carico a')->placeholder('Da assegnare'),
                TextColumn::make('amount')->label('Importo')->money('EUR')->sortable(),
                TextColumn::make('data_inserimento_pratica')->label('Inserita il')->date('d/m/Y')->sortable(),
            ])
            ->recordUrl(fn (Pratica $record): ?string => static::canEdit($record) ? static::getUrl('edit', ['record' => $record]) : null)
            ->recordActions([
                EditAction::make()->label('Perfeziona')->visible(fn (Pratica $record): bool => static::canEdit($record)),
                RinunciaClienteAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            DocumentsRelationManager::class,
            ProvvigioniRelationManager::class,
            WebsitesRelationManager::class,
            BranchesRelationManager::class,
            AddressesRelationManager::class,
            CointestatariGarantiRelationManager::class,
            ImmobileRelationManager::class,
            PraticheRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPratiche::route('/'),
            'edit' => EditPratica::route('/{record}/edit'),
        ];
    }
}
