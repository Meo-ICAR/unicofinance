<?php

namespace App\Filament\Resources\RequestRegistries;

use App\Filament\Resources\RequestRegistries\Pages\CreateRequestRegistry;
use App\Filament\Resources\RequestRegistries\Pages\EditRequestRegistry;
use App\Filament\Resources\RequestRegistries\Pages\ListRequestRegistries;
use App\Filament\Resources\RequestRegistries\RelationManagers\ActionsRelationManager;
use App\Filament\Resources\RequestRegistries\RelationManagers\AttachmentsRelationManager;
use App\Filament\Resources\RequestRegistries\RelationManagers\ProcessesRelationManager;
use App\Models\Client;
use App\Models\Employee;
use App\Models\RequestRegistry;
use App\Models\User;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class RequestRegistryResource extends Resource
{
    protected static ?string $model = RequestRegistry::class;

    protected static string|UnitEnum|null $navigationGroup = 'Compliance & Privacy';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // ── Identificazione ──────────────────────────────
                self::sectionIdentificazione(),

                // ── Richiedente ──────────────────────────────────
                self::sectionRichiedente(),

                // ── Oggetto della Richiesta ──────────────────────
                self::sectionOggetto(),

                // ── Gestione e Risposta ──────────────────────────
                self::sectionGestione(),
            ])
            ->columns(1);
    }

    // ────────────────────────────────────────────────────────────────
    // SEZIONI DEL FORM
    // ────────────────────────────────────────────────────────────────

    private static function sectionIdentificazione(): \Filament\Schemas\Components\Section
    {
        return \Filament\Schemas\Components\Section::make('Identificazione Richiesta')
            ->schema([
                TextInput::make('request_number')
                    ->label('Numero Protocollo')
                    ->disabled()
                    ->dehydrated()
                    ->helperText('Generato automaticamente')
                    ->columnSpan(1),
                DatePicker::make('request_date')
                    ->label('Data Ricezione')
                    ->required()
                    ->native(false)
                    ->columnSpan(1),
                Select::make('received_via')
                    ->label('Canale di Ricezione')
                    ->options([
                        'email' => 'Email',
                        'pec' => 'PEC',
                        'telefono' => 'Telefono',
                        'raccomandata' => 'Raccomandata A/R',
                        'portale' => 'Portale Web',
                        'di_persona' => 'Di Persona',
                    ])
                    ->default('email')
                    ->required()
                    ->columnSpan(1),
            ])
            ->columns(3);
    }

    private static function sectionRichiedente(): \Filament\Schemas\Components\Section
    {
        return \Filament\Schemas\Components\Section::make('Richiedente')
            ->schema([
                Select::make('requester_type')
                    ->label('Tipo Richiedente')
                    ->options([
                        'interessato' => 'Interessato (diretto)',
                        'mandatario' => 'Mandatario (avvocato, consulente)',
                        'organismo_vigilanza' => 'Organismo di Vigilanza',
                    ])
                    ->default('interessato')
                    ->live()
                    ->required()
                    ->columnSpan(1),

                TextInput::make('requester_name')
                    ->label('Nome / Ragione Sociale')
                    ->required()
                    ->columnSpan(2),

                TextInput::make('requester_contact')
                    ->label('Contatto (email, telefono)')
                    ->columnSpan(2),

                // Campo condizionale: mandatario
                TextInput::make('mandate_reference')
                    ->label('Riferimenti Mandato/Procura')
                    ->helperText('Numero procura, data, notaio')
                    ->visible(fn (Get $get) => $get('requester_type') === 'mandatario')
                    ->columnSpanFull(),

                // Campo condizionale: organismo vigilanza
                Select::make('oversight_body_type')
                    ->label('Organismo di Vigilanza')
                    ->options([
                        'Garante Privacy' => 'Garante per la Protezione dei Dati Personali',
                        'ARERA' => 'ARERA (Autorità Energia)',
                        'AGCM' => 'AGCM (Antitrust)',
                        'Altro' => 'Altro',
                    ])
                    ->visible(fn (Get $get) => $get('requester_type') === 'organismo_vigilanza')
                    ->columnSpanFull(),
            ])
            ->columns(3);
    }

    private static function sectionOggetto(): \Filament\Schemas\Components\Section
    {
        return \Filament\Schemas\Components\Section::make('Oggetto della Richiesta')
            ->schema([
                Select::make('request_type')
                    ->label('Tipo di Richiesta')
                    ->options([
                        'accesso' => 'Accesso (Art. 15)',
                        'cancellazione' => 'Cancellazione / Oblio (Art. 17)',
                        'rettifica' => 'Rettifica (Art. 16)',
                        'opposizione' => 'Opposizione (Art. 21)',
                        'limitazione' => 'Limitazione del Trattamento (Art. 18)',
                        'portabilita' => 'Portabilità dei Dati (Art. 20)',
                        'revoca_consenso' => 'Revoca del Consenso',
                        'reclamazione' => 'Reclamazione (Art. 77)',
                    ])
                    ->required()
                    ->columnSpanFull(),

                Select::make('data_subject_type')
                    ->label('Tipo Soggetto Interessato')
                    ->options([
                        Client::class => 'Cliente / Lead',
                        Employee::class => 'Dipendente / Collaboratore',
                        User::class => 'Utente di Sistema',
                    ])
                    ->live()
                    ->columnSpan(1),

                Select::make('data_subject_id')
                    ->label('Soggetto Interessato')
                    ->options(fn (Get $get) => self::getDataSubjectOptions($get('data_subject_type')))
                    ->searchable()
                    ->visible(fn (Get $get) => filled($get('data_subject_type')))
                    ->columnSpan(2),

                Textarea::make('description')
                    ->label('Descrizione della Richiesta')
                    ->rows(4)
                    ->columnSpanFull(),
            ])
            ->columns(3);
    }

    private static function sectionGestione(): \Filament\Schemas\Components\Section
    {
        return \Filament\Schemas\Components\Section::make('Gestione e Risposta')
            ->schema([
                Select::make('status')
                    ->label('Stato')
                    ->options([
                        'ricevuta' => 'Ricevuta',
                        'in_lavorazione' => 'In Lavorazione',
                        'evasa' => 'Evasa',
                        'respinta' => 'Respinta',
                        'parzialmente_evasa' => 'Parzialmente Evasa',
                        'scaduta' => 'Scaduta',
                    ])
                    ->default('ricevuta')
                    ->required()
                    ->columnSpan(1),

                Select::make('assigned_to')
                    ->label('Assegnata a')
                    ->options(User::query()->pluck('name', 'id'))
                    ->searchable()
                    ->columnSpan(1),

                DatePicker::make('response_deadline')
                    ->label('Scadenza (30 gg)')
                    ->native(false)
                    ->columnSpan(1),

                DatePicker::make('response_date')
                    ->label('Data Risposta')
                    ->native(false)
                    ->columnSpan(1),

                MarkdownEditor::make('response_summary')
                    ->label('Sintesi della Risposta')
                    ->rows(4)
                    ->columnSpanFull(),

                // SLA
                \Filament\Schemas\Components\Grid::make(3)
                    ->schema([
                        Toggle::make('sla_breach')
                            ->label('SLA Breach')
                            ->disabled()
                            ->helperText('Calcolato automaticamente'),
                        Toggle::make('extension_granted')
                            ->label('Estensione a 90 gg (Art. 12.3)')
                            ->live(),
                    ]),

                Textarea::make('extension_reason')
                    ->label('Motivazione Estensione')
                    ->rows(2)
                    ->visible(fn (Get $get) => $get('extension_granted'))
                    ->columnSpanFull(),

                Textarea::make('notes')
                    ->label('Note Interne')
                    ->rows(3)
                    ->columnSpanFull(),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('request_number')
                    ->label('Protocollo')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('request_date')
                    ->label('Data Richiesta')
                    ->date('d/m/Y')
                    ->sortable(),

                BadgeColumn::make('requester_type')
                    ->label('Richiedente')
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'interessato' => 'Interessato',
                        'mandatario' => 'Mandatario',
                        'organismo_vigilanza' => 'Org. Vigilanza',
                    })
                    ->colors([
                        'success' => 'interessato',
                        'warning' => 'mandatario',
                        'danger' => 'organismo_vigilanza',
                    ]),

                BadgeColumn::make('request_type')
                    ->label('Tipo')
                    ->capitalize()
                    ->colors([
                        'info' => 'accesso',
                        'danger' => 'cancellazione',
                        'warning' => fn (string $state) => in_array($state, ['opposizione', 'revoca_consenso']),
                        'success' => 'portabilita',
                        'gray' => 'reclamazione',
                    ]),

                TextColumn::make('assignedUser.name')
                    ->label('Assegnata a')
                    ->placeholder('—'),

                TextColumn::make('response_deadline')
                    ->label('Scadenza')
                    ->date('d/m/Y')
                    ->sortable()
                    ->color(fn (string $state) => strtotime($state) < now()->timestamp ? Color::Red : null),

                BadgeColumn::make('status')
                    ->label('Stato')
                    ->formatStateUsing(fn (string $state) => ucfirst(str_replace('_', ' ', $state)))
                    ->colors([
                        'gray' => 'ricevuta',
                        'info' => 'in_lavorazione',
                        'success' => 'evasa',
                        'danger' => 'respinta',
                        'warning' => 'parzialmente_evasa',
                        'danger' => 'scaduta',
                    ]),

                IconColumn::make('sla_breach')
                    ->label('SLA ⚠')
                    ->boolean()
                    ->trueColor(Color::Red),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Stato')
                    ->options([
                        'ricevuta' => 'Ricevuta',
                        'in_lavorazione' => 'In Lavorazione',
                        'evasa' => 'Evasa',
                        'respinta' => 'Respinta',
                        'parzialmente_evasa' => 'Parzialmente Evasa',
                        'scaduta' => 'Scaduta',
                    ]),

                SelectFilter::make('request_type')
                    ->label('Tipo Richiesta')
                    ->options([
                        'accesso' => 'Accesso',
                        'cancellazione' => 'Cancellazione',
                        'rettifica' => 'Rettifica',
                        'opposizione' => 'Opposizione',
                        'limitazione' => 'Limitazione',
                        'portabilita' => 'Portabilità',
                        'revoca_consenso' => 'Revoca Consenso',
                        'reclamazione' => 'Reclamazione',
                    ]),

                SelectFilter::make('requester_type')
                    ->label('Tipo Richiedente')
                    ->options([
                        'interessato' => 'Interessato',
                        'mandatario' => 'Mandatario',
                        'organismo_vigilanza' => 'Organismo Vigilanza',
                    ]),

                SelectFilter::make('assigned_to')
                    ->label('Assegnata a')
                    ->options(User::query()->pluck('name', 'id'))
                    ->searchable(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('request_date', 'desc')
            ->paginated([10, 25, 50, 100]);
    }

    public static function getRelations(): array
    {
        return [
            AttachmentsRelationManager::class,
            ActionsRelationManager::class,
            ProcessesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRequestRegistries::route('/'),
            'create' => CreateRequestRegistry::route('/create'),
            'edit' => EditRequestRegistry::route('/{record}/edit'),
        ];
    }

    // ────────────────────────────────────────────────────────────────
    // Helpers
    // ────────────────────────────────────────────────────────────────

    private static function getDataSubjectOptions(?string $type): array
    {
        if (! $type) {
            return [];
        }

        return match ($type) {
            Client::class => Client::query()->pluck('name', 'id')->toArray(),
            Employee::class => Employee::query()->pluck('name', 'id')->toArray(),
            User::class => User::query()->pluck('name', 'id')->toArray(),
            default => [],
        };
    }
}
