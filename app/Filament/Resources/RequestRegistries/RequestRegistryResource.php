<?php

namespace App\Filament\Resources\RequestRegistries;

use App\Enums\RequestType;
use App\Filament\Resources\RequestRegistries\Pages\CreateRequestRegistry;
use App\Filament\Resources\RequestRegistries\Pages\EditRequestRegistry;
use App\Filament\Resources\RequestRegistries\Pages\ListRequestRegistries;
use App\Filament\Resources\RequestRegistries\RelationManagers\ActionsRelationManager;
use App\Filament\Resources\RequestRegistries\RelationManagers\AttachmentsRelationManager;
use App\Filament\Resources\RequestRegistries\RelationManagers\ProcessesRelationManager;
use App\Models\Client;
use App\Models\Employee;
use App\Models\ProcessRequestMapping;
use App\Models\RequestRegistry;
use App\Models\User;
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
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use BackedEnum;
use UnitEnum;
use \Filament\Schemas\Components\Section;

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

    private static function sectionRichiedente(): Section
    {
        return Section::make('Richiedente')
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
                    //   ->visible(fn (Get $get) => $get('requester_type') === 'mandatario')
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
                    //   ->visible(fn (Get $get) => $get('requester_type') === 'organismo_vigilanza')
                    ->columnSpanFull(),
            ])
            ->columns(3);
    }

    private static function sectionOggetto(): Section
    {
        return Section::make('Oggetto della Richiesta')
            ->schema([
                Select::make('request_type')
                    ->label('Tipo di Richiesta')
                    ->options(RequestType::options())
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
                    ->options(fn($get) => self::getDataSubjectOptions($get('data_subject_type')))
                    ->searchable()
                    //  ->visible(fn (Get $get) => filled($get('data_subject_type')))
                    ->columnSpan(2),
                Textarea::make('description')
                    ->label('Descrizione della Richiesta')
                    ->rows(4)
                    ->columnSpanFull(),
            ])
            ->columns(3);
    }

    private static function sectionGestione(): Section
    {
        return Section::make('Gestione e Risposta')
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
                    ->options(function ($get) {
                        $processId = $get('active_process_id');
                        if (!$processId) {
                            return User::query()->pluck('name', 'id');
                        }

                        // Ottieni gli utenti RACI per questo processo
                        $raciAssignments = DB::table('raci_assignments')
                            ->where('process_id', $processId)
                            ->pluck('role', 'business_function_id');

                        // Mappa business function a utenti
                        $users = collect();
                        foreach ($raciAssignments as $businessFunctionId => $role) {
                            $functionUsers = User::whereHas('businessFunctions', function ($query) use ($businessFunctionId) {
                                $query->where('business_functions.id', $businessFunctionId);
                            })->pluck('name', 'id');

                            $users = $users->merge($functionUsers);
                        }

                        // Aggiungi tutti gli utenti come fallback
                        $allUsers = User::query()->pluck('name', 'id');

                        return $allUsers->unique();
                    })
                    ->searchable()
                    ->placeholder('Seleziona utente')
                    ->helperText('Utenti suggeriti basati su matrice RACI del processo')
                    ->columnSpan(1),
                // Processo BPM
                Select::make('active_process_id')
                    ->label('Processo BPM')
                    ->options(function ($get) {
                        $requestType = $get('request_type');
                        if (!$requestType) {
                            return [];
                        }

                        return ProcessRequestMapping::query()
                            ->join('processes', 'processes.id', '=', 'process_request_mappings.process_id')
                            ->where('process_request_mappings.request_type', $requestType)
                            ->where('processes.is_active', 1)
                            ->select('processes.id', 'processes.name', 'process_request_mappings.is_suggested')
                            ->orderBy('process_request_mappings.is_suggested', 'desc')
                            ->orderBy('processes.name')
                            ->pluck('processes.name', 'processes.id')
                            ->toArray();
                    })
                    ->searchable()
                    ->placeholder('Seleziona processo')
                    ->helperText('Processi suggeriti per questo tipo di richiesta')
                    ->columnSpan(1),
                Select::make('process_task_id')
                    ->label('Task Corrente')
                    ->relationship('activeTask', 'name')
                    ->searchable()
                    ->placeholder('Seleziona task')
                    ->helperText('Task specifico ("scrivania") corrente')
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
                    // ->rows(4)
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
                    ->visible(fn($get) => $get('extension_granted'))
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
                TextColumn::make('requester_type')
                    ->label('Richiedente')
                    ->badge()
                    ->formatStateUsing(fn(string $state) => match ($state) {
                        'interessato' => 'Interessato',
                        'mandatario' => 'Mandatario',
                        'organismo_vigilanza' => 'Org. Vigilanza',
                    })
                    ->colors([
                        'success' => 'interessato',
                        'warning' => 'mandatario',
                        'danger' => 'organismo_vigilanza',
                    ]),
                TextColumn::make('request_type')
                    ->label('Tipo')
                    ->badge()
                    // ->capitalize()
                    ->colors([
                        'info' => 'accesso',
                        'danger' => 'cancellazione',
                        'warning' => fn(string $state) => in_array($state, ['opposizione', 'revoca_consenso']),
                        'success' => 'portabilita',
                        'gray' => 'reclamazione',
                    ]),
                TextColumn::make('activeProcess.name')
                    ->label('Processo BPM')
                    ->placeholder('Non assegnato')
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('assignedUser.name')
                    ->label('Assegnata a')
                    ->placeholder('Nessuna'),
                TextColumn::make('activeTask.name')
                    ->label('Task Corrente')
                    ->disabled()
                    ->placeholder('Nessuno')
                    ->badge()
                    ->color('success')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('response_deadline')
                    ->label('Scadenza')
                    ->date('d/m/Y')
                    ->sortable()
                    ->color(fn(string $state) => strtotime($state) < now()->timestamp ? Color::Red : null),
                BadgeColumn::make('status')
                    ->label('Stato')
                    ->formatStateUsing(fn(string $state) => ucfirst(str_replace('_', ' ', $state)))
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
                    ->options(RequestType::shortOptions()),
                SelectFilter::make('requester_type')
                    ->label('Tipo Richiedente')
                    ->options([
                        'interessato' => 'Interessato',
                        'mandatario' => 'Mandatario',
                        'organismo_vigilanza' => 'Organismo Vigilanza',
                    ]),
                SelectFilter::make('assigned_to')
                    ->label('Assegnata a')
                    ->options(User::query()->pluck('name', 'id')),
                SelectFilter::make('active_process_id')
                    ->label('Processo BPM')
                    ->relationship('activeProcess', 'name')
                    ->searchable()
                    ->placeholder('Tutti'),
                SelectFilter::make('process_task_id')
                    ->label('Task Corrente')
                    ->relationship('activeTask', 'name')
                    ->searchable()
                    ->placeholder('Tutti'),
            ])
            ->actions([
                CreateAction::make('create_task_execution')
                    ->label('Crea Esecuzione Task')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->url(fn ($record) => route('filament.resources.task-executions.create', [
                        'process_id' => $record->active_process_id,
                        'task_id' => $record->process_task_id,
                        'request_registry_id' => $record->id,
                    ]))
                    ->visible(fn ($record) => {
                        // Mostra solo se processo e task sono assegnati
                        if (!$record->active_process_id || !$record->process_task_id) {
                            return false;
                        }

                        // Controlla se esiste già un'esecuzione attiva per questo task
                        $existingExecution = \App\Models\TaskExecution::where('process_task_id', $record->process_task_id)
                            ->where('request_registry_id', $record->id)
                            ->whereIn('status', ['in_progress', 'pending'])
                            ->exists();

                        // Disabilita se c'è già un'esecuzione attiva
                        return !$existingExecution;
                    }),
                EditAction::make()
                    ->color('warning'),
                DeleteAction::make()
                    ->color('danger'),
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
        if (!$type) {
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
