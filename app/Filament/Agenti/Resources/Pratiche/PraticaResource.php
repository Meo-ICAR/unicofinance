<?php

namespace App\Filament\Agenti\Resources\Pratiche;

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
use App\Models\Pratica;
use App\Services\Agenti\AgentImpersonationService;
use App\Services\CodiceFiscaleDecoder;
use App\Services\PortaleBanca;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
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
use Unico\Core\Filament\RelationManagers\DocumentsRelationManager;
use Unico\Core\Models\Cliente;
use Unico\Core\Models\TipoProdotto;

class PraticaResource extends Resource
{
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

    public static function canEdit(Model $record): bool
    {
        return true;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $fornitoreId = app(AgentImpersonationService::class)->agentFornitoreId();
        $query = parent::getEloquentQuery();

        if ($fornitoreId === null) {
            return $query->whereRaw('0 = 1');
        }

        return $query->where('fornitori_id', $fornitoreId);
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

        return $schema->components([
            Section::make('Richiedente')
                ->icon('heroicon-o-user')
                ->columns(4)
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
                ->columns(3)
                ->columnSpanFull()
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
                    TextInput::make('rata')
                        ->label('Rata')
                        ->numeric()
                        ->prefix('€')
                        ->visible($sulla_rata),
                    TextInput::make('amount')
                        ->label('Importo richiesto')
                        ->numeric()
                        ->prefix('€')
                        ->visible(fn (Get $get): bool => ! $sulla_rata($get)),
                    TextInput::make('nrate')
                        ->label('Durata (mesi)')
                        ->numeric()
                        ->integer()
                        ->visible(fn (Get $get): bool => ! $sulla_rata($get)),
                    TextInput::make('codice_pratica')->label('Numero pratica')->maxLength(255),
                    TextInput::make('codice_pratica_banca')->label('Codice pratica banca')->helperText('Assegnato dalla banca sul suo portale')->maxLength(100),
                    DatePicker::make('data_inserimento_pratica')
                        ->label('Data richiesta')
                        ->native(false)
                        ->displayFormat('d/m/Y'),
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
                            Select::make('pratica_stato_id')
                                ->label('Stato')
                                ->relationship('praticaStato', 'codice')
                                ->searchable()
                                ->preload(),
                            TextInput::make('denominazione_prodotto')->label('Prodotto')->maxLength(191),
                            TextInput::make('net')->label('Importo netto')->numeric()->prefix('€'),
                            TextInput::make('erogato')->label('Erogato')->numeric()->prefix('€'),
                            TextInput::make('denominazione_agente')->label('Agente')->maxLength(191),
                            DatePicker::make('sended_at')->label('Invio a banca')->native(false)->displayFormat('d/m/Y'),
                            DatePicker::make('approved_at')->label('Approvazione')->native(false)->displayFormat('d/m/Y'),
                            DatePicker::make('erogated_at')->label('Erogazione')->native(false)->displayFormat('d/m/Y'),
                            DatePicker::make('rejected_at')->label('Rifiuto')->native(false)->displayFormat('d/m/Y'),
                        ])
                        ->columns(2),
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
            ->defaultSort('data_inserimento_pratica', 'desc')
            ->columns([
                TextColumn::make('codice_pratica')->label('Codice')->searchable()->sortable(),
                TextColumn::make('cognome_cliente')->label('Cognome')->searchable()->sortable(),
                TextColumn::make('nome_cliente')->label('Nome')->searchable(),
                TextColumn::make('tipoprodotto.name')->label('Tipo prodotto')->sortable(),
                TextColumn::make('praticaStato.codice')->label('Stato')->badge(),
                TextColumn::make('amount')->label('Importo')->money('EUR')->sortable(),
                TextColumn::make('data_inserimento_pratica')->label('Inserita il')->date('d/m/Y')->sortable(),
            ])
            ->recordUrl(fn (Pratica $record): string => static::getUrl('edit', ['record' => $record]))
            ->recordActions([
                EditAction::make()->label('Perfeziona'),
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
