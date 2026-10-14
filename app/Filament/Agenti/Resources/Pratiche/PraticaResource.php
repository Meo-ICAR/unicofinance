<?php

namespace App\Filament\Agenti\Resources\Pratiche;

use App\Filament\Agenti\Resources\Pratiche\Pages\EditPratica;
use App\Filament\Agenti\Resources\Pratiche\Pages\ListPratiche;
use App\Filament\Agenti\Resources\Pratiche\Schemas\ClienteApiActions;
use App\Filament\RelationManagers\AddressesRelationManager;
use App\Filament\RelationManagers\BranchesRelationManager;
use Unico\Core\Filament\RelationManagers\DocumentsRelationManager;
use App\Filament\RelationManagers\PraticheRelationManager;
use App\Filament\RelationManagers\ProvvigioniRelationManager;
use App\Filament\RelationManagers\WebsitesRelationManager;
use App\Filament\Resources\Clients\Schemas\KycQuestionnaireForm;
use App\Models\Pratica;
use App\Services\Agenti\AgentImpersonationService;
use App\Services\CodiceFiscaleDecoder;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

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

    public static function form(Schema $schema): Schema
    {
        $isPerson = fn (?Get $get = null): bool => $get instanceof Get && (bool) $get('../is_person');

        return $schema->components([
            Tabs::make('Pratica')
                ->columnSpanFull()
                ->tabs([
                    Tab::make('Finanziamento')
                        ->icon('heroicon-o-banknotes')
                        ->schema([
                            TextInput::make('codice_pratica')->label('Codice')->disabled()->dehydrated(false),
                            Select::make('tipoprodotto_id')
                                ->label('Tipo prodotto')
                                ->relationship('tipoprodotto', 'name')
                                ->searchable()
                                ->preload(),
                            Select::make('pratica_stato_id')
                                ->label('Stato')
                                ->relationship('praticaStato', 'codice')
                                ->searchable()
                                ->preload(),
                            TextInput::make('denominazione_banca')->label('Banca')->maxLength(191),
                            TextInput::make('denominazione_prodotto')->label('Prodotto')->maxLength(191),
                            TextInput::make('amount')->label('Importo')->numeric()->prefix('€'),
                            TextInput::make('net')->label('Importo netto')->numeric()->prefix('€'),
                            TextInput::make('erogato')->label('Erogato')->numeric()->prefix('€'),
                            TextInput::make('rata')->label('Rata')->numeric()->prefix('€'),
                            TextInput::make('nrate')->label('Rate')->numeric()->integer(),
                            DatePicker::make('data_inserimento_pratica')
                                ->label('Inserita il')
                                ->native(false)
                                ->displayFormat('d/m/Y'),
                        ])
                        ->columns(2),
                    Tab::make('Cliente')
                        ->icon('heroicon-o-user')
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
                                    ClienteApiActions::cerved(),
                                ]),
                            Section::make('Contatti del cliente')
                                ->description('Email e cellulare sono obbligatori per perfezionare la pratica.')
                                ->columns(2)
                                ->schema([
                                    TextInput::make('cliente_email')
                                        ->label('Email')
                                        ->email()
                                        ->required()
                                        ->maxLength(255)
                                        ->dehydrated(false),
                                    TextInput::make('cliente_cellulare')
                                        ->label('Cellulare')
                                        ->tel()
                                        ->required()
                                        ->maxLength(50)
                                        ->dehydrated(false),
                                    TextInput::make('cliente_iban')
                                        ->label('IBAN')
                                        ->maxLength(34)
                                        ->dehydrated(false)
                                        ->dehydrateStateUsing(fn (?string $state): ?string => filled($state)
                                            ? strtoupper(str_replace(' ', '', $state))
                                            : null)
                                        ->suffixActions([
                                            ClienteApiActions::openapi(),
                                        ]),
                                ]),
                            Section::make('Anagrafica richiedente')
                                ->description('Dati del cliente collegato dal codice fiscale.')
                                ->relationship('richiedente')
                                ->visible(fn (?Pratica $record): bool => $record?->richiedente !== null)
                                ->columns(2)
                                ->schema([
                                    TextInput::make('name')->label('Cognome / ragione sociale')->required()->maxLength(255),
                                    TextInput::make('first_name')->label('Nome')->maxLength(255),
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
                                    Toggle::make('is_person')->label('Persona fisica')->live(),
                                    DatePicker::make('birth_date')->label('Data di nascita')->native(false)->displayFormat('d/m/Y'),
                                    TextInput::make('birth_place')->label('Luogo di nascita')->maxLength(100),
                                    Select::make('sex')->label('Sesso')->options(['M' => 'M', 'F' => 'F']),
                                    TextInput::make('citizenship')->label('Cittadinanza')->maxLength(60),
                                    TextInput::make('salary')->label('Reddito')->numeric()->prefix('€'),
                                    Section::make('Valutazione rischio (AML)')
                                        ->key('valutazioneAml')
                                        ->columns(3)
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
                                        ->columns(2)
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
                                ]),
                        ])
                        ->columns(2),
                    Tab::make('Dettagli')
                        ->icon('heroicon-o-clipboard-document-list')
                        ->schema([
                            TextInput::make('denominazione_agente')->label('Agente')->maxLength(191),
                            TextInput::make('abi')->label('ABI')->maxLength(20),
                            Toggle::make('is_notowned')->label('Impegno di terzi'),
                            DatePicker::make('sended_at')->label('Invio a banca')->native(false)->displayFormat('d/m/Y'),
                            DatePicker::make('approved_at')->label('Approvazione')->native(false)->displayFormat('d/m/Y'),
                            DatePicker::make('erogated_at')->label('Erogazione')->native(false)->displayFormat('d/m/Y'),
                            DatePicker::make('rejected_at')->label('Rifiuto')->native(false)->displayFormat('d/m/Y'),
                        ])
                        ->columns(2),
                ]),
        ]);
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
