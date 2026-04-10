<?php

namespace App\Filament\Resources\Processes\Pages;

use App\Models\BusinessFunction;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\PrivacyDataType;
use App\Models\PrivacyLegalBase;
use App\Models\Process;
use App\Models\ProcessTask;
use App\Models\ProcessTaskPrivacyData;
use App\Models\RaciAssignment;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Wizard;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;

class CreateProcessWizard extends CreateRecord
{
    protected static string $resource = \App\Filament\Resources\Processes\ProcessesResource::class;

    protected static bool $canCreateAnother = false;

    public static function schema(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make([
                    Wizard\Step::make('Informazioni Processo')
                        ->description('Nome, descrizione e funzioni di riferimento')
                        ->schema(static::processHeaderSchema()),

                    Wizard\Step::make('Task del Processo')
                        ->description('Definisci i passaggi, checklist, RACI e privacy')
                        ->schema(static::tasksSchema())
                        ->columns(1),
                ])
                    ->skippable()
                    ->contained(false),
            ]);
    }

    // ── Step 1: Process Header ──────────────────────────────────────

    protected static function processHeaderSchema(): array
    {
        return [
            TextInput::make('name')
                ->label('Nome Processo')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),

            Textarea::make('description')
                ->label('Descrizione')
                ->maxLength(65535)
                ->columnSpanFull(),

            Select::make('business_function_id')
                ->label('Funzione Aziendale Proprietaria')
                ->options(fn () => static::getBusinessFunctionOptions())
                ->searchable()
                ->required(),

            Select::make('owner_function_id')
                ->label('Funzione Supervisore (opzionale)')
                ->options(fn () => static::getBusinessFunctionOptions())
                ->searchable()
                ->nullable(),

            Select::make('target_model')
                ->label('Modello Target')
                ->options(static::getTargetModelOptions())
                ->searchable()
                ->nullable()
                ->placeholder('Seleziona un modello target'),

            Toggle::make('is_active')
                ->label('Attivo')
                ->default(true)
                ->inline(false),
        ];
    }

    // ── Step 2: Tasks with nested Checklists, RACI, Privacy ────────

    protected static function tasksSchema(): array
    {
        return [
            Repeater::make('tasks')
                ->label('Task del Processo')
                ->schema([
                    Fieldset::make('Task')
                        ->schema([
                            TextInput::make('name')
                                ->label('Nome Task')
                                ->required()
                                ->maxLength(255)
                                ->live()
                                ->columnSpanFull(),

                            Textarea::make('description')
                                ->label('Descrizione')
                                ->maxLength(65535)
                                ->columnSpanFull(),

                            Select::make('business_function_id')
                                ->label('Funzione Aziendale')
                                ->options(fn () => static::getBusinessFunctionOptions())
                                ->searchable()
                                ->required(),
                        ]),

                    // ── Nested Repeater: Checklists & Items ──
                    Repeater::make('checklists')
                        ->label('Checklist')
                        ->collapsible()
                        ->collapsed()
                        ->cloneable()
                        ->reorderable()
                        ->itemLabel(fn (array $state): ?string => $state['name'] ?? 'Nuova Checklist')
                        ->schema([
                            TextInput::make('name')
                                ->label('Nome Checklist')
                                ->required()
                                ->maxLength(255)
                                ->live()
                                ->columnSpanFull(),

                            Textarea::make('description')
                                ->label('Descrizione')
                                ->maxLength(65535)
                                ->columnSpanFull(),

                            Repeater::make('items')
                                ->label('Elementi')
                                ->collapsible()
                                ->cloneable()
                                ->reorderable()
                                ->itemLabel(fn (array $state): ?string => $state['instruction'] ?? 'Nuovo elemento')
                                ->schema([
                                    Textarea::make('instruction')
                                        ->label('Istruzione')
                                        ->required()
                                        ->columnSpanFull(),

                                    Toggle::make('is_mandatory')
                                        ->label('Obbligatorio')
                                        ->default(true),

                                    Select::make('require_condition_class')
                                        ->label('Regola di Richiesta')
                                        ->options(static::getRuleOptions())
                                        ->searchable()
                                        ->nullable(),

                                    Select::make('skip_condition_class')
                                        ->label('Regola di Salto')
                                        ->options(static::getRuleOptions())
                                        ->searchable()
                                        ->nullable(),

                                    Select::make('action_class')
                                        ->label('Azione al Completamento')
                                        ->options(static::getActionOptions())
                                        ->searchable()
                                        ->nullable(),

                                    TextInput::make('sort_order')
                                        ->label('Ordine')
                                        ->numeric()
                                        ->default(0),
                                ])
                                ->columns(2)
                                ->defaultItems(0)
                                ->addActionLabel('Aggiungi elemento'),
                        ])
                        ->columns(2)
                        ->defaultItems(0)
                        ->addActionLabel('Aggiungi checklist'),

                    // ── Nested Repeater: RACI Assignments ──
                    Repeater::make('raci_assignments')
                        ->label('Matrice RACI')
                        ->schema([
                            Select::make('business_function_id')
                                ->label('Funzione Aziendale')
                                ->options(fn () => static::getBusinessFunctionOptions())
                                ->searchable()
                                ->required(),

                            Select::make('role')
                                ->label('Ruolo')
                                ->options([
                                    'R' => 'R — Responsabile (esegue)',
                                    'A' => 'A — Approvatore (accountable)',
                                    'C' => 'C — Consultato',
                                    'I' => 'I — Informato',
                                ])
                                ->required()
                                ->default('R'),
                        ])
                        ->columns(2)
                        ->defaultItems(4)
                        ->minItems(1)
                        ->addActionLabel('Aggiungi ruolo RACI'),

                    // ── Nested Repeater: Privacy Data ──
                    Repeater::make('privacy_data')
                        ->label('Dati Privacy (GDPR)')
                        ->collapsible()
                        ->collapsed()
                        ->cloneable()
                        ->itemLabel(function (array $state): ?string {
                            if (! $state['privacy_data_type_id']) {
                                return 'Nuovo dato privacy';
                            }

                            return PrivacyDataType::find($state['privacy_data_type_id'])?->name ?? 'Dato privacy';
                        })
                        ->schema([
                            Select::make('privacy_data_type_id')
                                ->label('Tipo Dato')
                                ->options(fn () => PrivacyDataType::pluck('name', 'id')->toArray())
                                ->searchable()
                                ->required(),

                            Select::make('access_level')
                                ->label('Livello Accesso')
                                ->options([
                                    'read' => 'Lettura',
                                    'write' => 'Scrittura',
                                    'update' => 'Aggiornamento',
                                    'delete' => 'Cancellazione',
                                ])
                                ->default('read')
                                ->required(),

                            Textarea::make('purpose')
                                ->label('Finalità del Trattamento')
                                ->columnSpanFull(),

                            Select::make('privacy_legal_base_id')
                                ->label('Base Legale')
                                ->options(fn () => PrivacyLegalBase::pluck('name', 'id')->toArray())
                                ->searchable()
                                ->nullable(),

                            TextInput::make('retention_period')
                                ->label('Periodo di Conservazione')
                                ->nullable(),

                            Toggle::make('is_encrypted')
                                ->label('Dati Crittografati')
                                ->default(false),

                            Toggle::make('is_shared_externally')
                                ->label('Condiviso con Esterni')
                                ->default(false),
                        ])
                        ->columns(2)
                        ->defaultItems(0)
                        ->addActionLabel('Aggiungi dato privacy'),
                ])
                ->columns(1)
                ->defaultItems(1)
                ->minItems(1)
                ->addActionLabel('Aggiungi task'),
        ];
    }

    // ── Helper Methods ──────────────────────────────────────────────

    protected static function getBusinessFunctionOptions(): array
    {
        $user = auth()->user();
        if (! $user) {
            return [];
        }

        $companyIds = $user->companies()->pluck('company_id');

        return BusinessFunction::whereIn('company_id', $companyIds)
            ->pluck('name', 'id')
            ->toArray();
    }

    protected static function getTargetModelOptions(): array
    {
        $models = [];
        foreach (glob(app_path('Models/*.php')) as $file) {
            $model = basename($file, '.php');
            $models["App\\Models\\{$model}"] = preg_replace('/(?<!^)[A-Z]/', ' $0', $model);
        }

        return $models;
    }

    protected static function getRuleOptions(): array
    {
        $rules = [];
        foreach (glob(app_path('Rules/Bpm/*.php')) as $file) {
            $rule = basename($file, '.php');
            $rules["App\\Rules\\Bpm\\{$rule}"] = preg_replace('/(?<!^)[A-Z]/', ' $0', $rule);
        }

        return $rules;
    }

    protected static function getActionOptions(): array
    {
        $actions = [];
        foreach (glob(app_path('Actions/Bpm/*.php')) as $file) {
            $action = basename($file, '.php');
            $actions["App\\Actions\\Bpm\\{$action}"] = preg_replace('/(?<!^)[A-Z]/', ' $0', $action);
        }

        return $actions;
    }

    // ── Persist ─────────────────────────────────────────────────────

    protected function handleRecordCreation(array $data): Process
    {
        $user = auth()->user();
        $companyId = $user->current_company_id ?? $user->companies()->first()?->id;

        return DB::transaction(function () use ($data, $companyId) {
            // 1. Create Process
            $process = Process::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'business_function_id' => $data['business_function_id'],
                'owner_function_id' => $data['owner_function_id'] ?? null,
                'target_model' => $data['target_model'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'company_id' => $companyId,
            ]);

            // 2. Create Tasks
            $taskDataList = $data['tasks'] ?? [];
            foreach ($taskDataList as $taskIndex => $taskData) {
                $task = ProcessTask::create([
                    'company_id' => $companyId,
                    'process_id' => $process->id,
                    'name' => $taskData['name'],
                    'description' => $taskData['description'] ?? null,
                    'business_function_id' => $taskData['business_function_id'],
                    'sequence_number' => $taskIndex + 1,
                ]);

                // 2a. RACI assignments — replace auto-created defaults
                RaciAssignment::where('process_task_id', $task->id)->delete();

                foreach ($taskData['raci_assignments'] ?? [] as $raciData) {
                    RaciAssignment::create([
                        'company_id' => $companyId,
                        'process_task_id' => $task->id,
                        'business_function_id' => $raciData['business_function_id'],
                        'role' => $raciData['role'],
                    ]);
                }

                // 2b. Checklists and Items
                foreach ($taskData['checklists'] ?? [] as $checklistIndex => $checklistData) {
                    $checklist = Checklist::create([
                        'process_task_id' => $task->id,
                        'name' => $checklistData['name'],
                        'description' => $checklistData['description'] ?? null,
                        'sort_order' => $checklistIndex + 1,
                    ]);

                    foreach ($checklistData['items'] ?? [] as $itemIndex => $itemData) {
                        ChecklistItem::create([
                            'checklist_id' => $checklist->id,
                            'instruction' => $itemData['instruction'],
                            'is_mandatory' => $itemData['is_mandatory'] ?? true,
                            'sort_order' => $itemIndex + 1,
                            'require_condition_class' => $itemData['require_condition_class'] ?? null,
                            'skip_condition_class' => $itemData['skip_condition_class'] ?? null,
                            'action_class' => $itemData['action_class'] ?? null,
                        ]);
                    }
                }

                // 2c. Privacy Data
                foreach ($taskData['privacy_data'] ?? [] as $privacyData) {
                    ProcessTaskPrivacyData::create([
                        'process_task_id' => $task->id,
                        'privacy_data_type_id' => $privacyData['privacy_data_type_id'],
                        'access_level' => $privacyData['access_level'] ?? 'read',
                        'purpose' => $privacyData['purpose'] ?? null,
                        'privacy_legal_base_id' => $privacyData['privacy_legal_base_id'] ?? null,
                        'retention_period' => $privacyData['retention_period'] ?? null,
                        'is_encrypted' => $privacyData['is_encrypted'] ?? false,
                        'is_shared_externally' => $privacyData['is_shared_externally'] ?? false,
                    ]);
                }
            }

            Notification::make()
                ->title('Processo Creato')
                ->body("Il processo '{$process->name}' è stato creato con {$process->tasks()->count()} task.")
                ->success()
                ->send();

            return $process;
        });
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
