<?php

namespace App\Filament\Pages;

use App\Models\Process;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Illuminate\Support\Facades\Route;
use BackedEnum;

class ProcessVisualizer extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $title = 'Process Visualizer';
    protected static ?int $navigationSort = 99;
    protected string $view = 'filament.pages.process-visualizer';

    public ?string $selectedProcessId = null;
    public string $diagramFormat = 'flowchart';
    public string $diagramDetail = 'tasks';
    public bool $showChecklistItems = true;

    public static function routes(Panel $panel, ?\Filament\Pages\PageConfiguration $configuration = null): void
    {
        $middleware = parent::getRouteMiddleware($panel);

        if ($configuration) {
            $middleware = [...$middleware, "page-configuration:{$configuration->getKey()}"];
        }

        Route::get(static::getRoutePath($panel).'/{processId?}', static::class)
            ->middleware($middleware)
            ->withoutMiddleware(parent::getWithoutRouteMiddleware($panel))
            ->name(static::getRelativeRouteName($panel));
    }

    public function mount(?string $processId = null): void
    {
        // Support URL segment: /process-visualizer/{processId}
        if ($processId) {
            $this->selectedProcessId = $processId;
        }

        // Support query parameter: ?process_id=...
        $queryParam = request()->query('process_id');
        if ($queryParam && ! $this->selectedProcessId) {
            $this->selectedProcessId = $queryParam;
        }

        $this->controlSchema->fill();
    }

    protected function getSchemas(): array
    {
        return [
            'controlSchema',
        ];
    }

    public function controlSchema(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Diagram Controls')
                    ->schema([
                        Grid::make(4)->schema([
                            Select::make('selectedProcessId')
                                ->label('Select Process')
                                ->placeholder('Choose a process to visualize')
                                ->options(fn (): array => $this->getProcessOptions())
                                ->live()
                                ->afterStateUpdated(function () {
                                    $this->dispatch('process-changed');
                                })
                                ->columnSpan(2),

                            ToggleButtons::make('diagramFormat')
                                ->label('Diagram Type')
                                ->options([
                                    'flowchart' => 'Flowchart',
                                    'stateDiagram' => 'State Diagram',
                                ])
                                ->colors([
                                    'flowchart' => 'primary',
                                    'stateDiagram' => 'warning',
                                ])
                                ->icons([
                                    'flowchart' => 'heroicon-o-arrows-right-left',
                                    'stateDiagram' => 'heroicon-o-arrow-path',
                                ])
                                ->inline()
                                ->default('flowchart')
                                ->live()
                                ->afterStateUpdated(function () {
                                    $this->dispatch('format-changed');
                                })
                                ->columnSpan(2),
                        ]),

                        Grid::make(3)->schema([
                            ToggleButtons::make('diagramDetail')
                                ->label('Detail Level')
                                ->options([
                                    'tasks' => 'Tasks Only',
                                    'checklists' => 'Tasks + Checklists',
                                    'full' => 'Full (RACI + Privacy)',
                                ])
                                ->colors([
                                    'tasks' => 'success',
                                    'checklists' => 'info',
                                    'full' => 'danger',
                                ])
                                ->icons([
                                    'tasks' => 'heroicon-o-square-3-stack-3d',
                                    'checklists' => 'heroicon-o-list-bullet',
                                    'full' => 'heroicon-o-information-circle',
                                ])
                                ->inline()
                                ->default('tasks')
                                ->live()
                                ->afterStateUpdated(function () {
                                    $this->dispatch('detail-changed');
                                })
                                ->columnSpan(2),

                            \Filament\Forms\Components\Checkbox::make('showChecklistItems')
                                ->label('Show Checklist Items')
                                ->default(true)
                                ->live()
                                ->afterStateUpdated(function () {
                                    $this->dispatch('items-visibility-changed');
                                }),
                        ]),
                    ])
                    ->collapsible(),
            ]);
    }

    protected function getProcessOptions(): array
    {
        return Process::query()
            ->where('company_id', Filament::getTenant()?->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn ($p) => [$p->id => $p->name])
            ->all();
    }

    public function getDiagramData(): ?string
    {
        if (! $this->selectedProcessId) {
            return null;
        }

        $process = Process::query()
            ->where('company_id', Filament::getTenant()?->id)
            ->with([
                'tasks.raciAssignments.businessFunction',
                'tasks.checklists.items',
                'tasks.privacyData.privacyDataType',
            ])
            ->find($this->selectedProcessId);

        if (! $process) {
            return null;
        }

        return $this->generateMermaidDefinition($process);
    }

    protected function generateMermaidDefinition(Process $process): string
    {
        $format = $this->diagramFormat;
        $detail = $this->diagramDetail;

        if ($format === 'stateDiagram') {
            return $this->generateStateDiagram($process, $detail);
        }

        return $this->generateFlowchart($process, $detail);
    }

    protected function generateFlowchart(Process $process, string $detail): string
    {
        $lines = [];
        $lines[] = 'flowchart TD';
        $lines[] = '    classDef processNode fill:#1e3a5f,stroke:#0f2439,stroke-width:2px,color:#fff,font-size:16px;';
        $lines[] = '    classDef taskNode fill:#3b82f6,stroke:#2563eb,stroke-width:2px,color:#fff;';
        $lines[] = '    classDef checklistNode fill:#10b981,stroke:#059669,stroke-width:1px,color:#fff,font-size:12px;';
        $lines[] = '    classDef itemNode fill:#8b5cf6,stroke:#7c3aed,stroke-width:1px,color:#fff,font-size:11px;';
        $lines[] = '    classDef raciNode fill:#f59e0b,stroke:#d97706,stroke-width:1px,color:#000,font-size:11px;';
        $lines[] = '    classDef privacyNode fill:#ef4444,stroke:#dc2626,stroke-width:1px,color:#fff,font-size:11px;';
        $lines[] = '';

        // Root process node
        $safeProcessName = $this->sanitizeLabel($process->name);
        $lines[] = "    P[\"{$safeProcessName}\"]:::processNode";
        $lines[] = '';

        if ($process->tasks->isEmpty()) {
            $lines[] = '    P --> EMPTY["No tasks defined"]';
            return implode("\n", $lines);
        }

        $showItems = in_array($detail, ['checklists', 'full']) && $this->showChecklistItems;

        // Task nodes
        foreach ($process->tasks as $task) {
            $taskId = "T{$task->id}";
            $safeTaskName = $this->sanitizeLabel($task->name);
            $stepLabel = "Step {$task->sequence_number}: {$safeTaskName}";

            $lines[] = "    {$taskId}[\"{$stepLabel}\"]:::taskNode";
            $lines[] = "    P --> {$taskId}";

            // Checklist detail
            if (in_array($detail, ['checklists', 'full'])) {
                foreach ($task->checklists as $checklist) {
                    $checklistId = "C{$checklist->id}";
                    $safeChecklistName = $this->sanitizeLabel($checklist->name);
                    $itemCount = $checklist->items->count();
                    $label = "{$safeChecklistName} ({$itemCount} items)";

                    $lines[] = "    {$checklistId}[\"{$label}\"]:::checklistNode";
                    $lines[] = "    {$taskId} --> {$checklistId}";

                    // Checklist items (rectangles)
                    if ($showItems && $checklist->items->isNotEmpty()) {
                        foreach ($checklist->items as $item) {
                            $itemId = "CI{$item->id}";
                            $safeInstruction = $this->sanitizeLabel($item->instruction);
                            $mandatoryBadge = $item->is_mandatory ? ' *' : '';
                            $itemLabel = "{$safeInstruction}{$mandatoryBadge}";

                            $lines[] = "    {$itemId}[\"{$itemLabel}\"]:::itemNode";
                            $lines[] = "    {$checklistId} --> {$itemId}";
                        }
                    }
                }
            }

            // RACI + Privacy (full detail)
            if ($detail === 'full') {
                // RACI
                $raciRoles = [];
                foreach ($task->raciAssignments as $raci) {
                    if ($raci->businessFunction) {
                        $raciRoles[$raci->role] = $raci->businessFunction->name;
                    }
                }
                if (! empty($raciRoles)) {
                    $raciId = "RACI{$task->id}";
                    $raciParts = [];
                    foreach ($raciRoles as $role => $funcName) {
                        $raciParts[] = "{$role}: {$this->sanitizeLabel($funcName)}";
                    }
                    $raciLabel = implode("\\n", $raciParts);
                    $lines[] = "    {$raciId}[\"{$raciLabel}\"]:::raciNode";
                    $lines[] = "    {$taskId} -.-> {$raciId}";
                }

                // Privacy
                foreach ($task->privacyData as $privacy) {
                    if ($privacy->privacyDataType) {
                        $privacyId = "PRIV{$task->id}_{$privacy->id}";
                        $typeLabel = $this->sanitizeLabel($privacy->privacyDataType->name);
                        $accessLabel = $this->sanitizeLabel($privacy->access_level ?? 'N/A');
                        $lines[] = "    {$privacyId}[\"&#128274; {$typeLabel}\\n({$accessLabel})\"]:::privacyNode";
                        $lines[] = "    {$taskId} -.-> {$privacyId}";
                    }
                }
            }

            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    protected function generateStateDiagram(Process $process, string $detail): string
    {
        $lines = [];
        $lines[] = 'stateDiagram-v2';
        $lines[] = "    [*] --> Start";
        $lines[] = '';

        if ($process->tasks->isEmpty()) {
            $lines[] = '    Start --> End';
            $lines[] = '    End --> [*]';
            return implode("\n", $lines);
        }

        $firstTask = $process->tasks->first();
        $firstTaskName = $this->sanitizeStateName($firstTask->name);
        $lines[] = "    Start --> State_{$firstTaskName}";

        foreach ($process->tasks as $index => $task) {
            $stateName = "State_{$this->sanitizeStateName($task->name)}";
            $safeName = $this->sanitizeLabel($task->name);

            $lines[] = "    state {$stateName} {";
            $lines[] = "        [*] --> Processing";
            $lines[] = "        Processing : {$safeName}";

            // Checklist items as states
            if (in_array($detail, ['checklists', 'full']) && $this->showChecklistItems) {
                foreach ($task->checklists as $checklist) {
                    foreach ($checklist->items as $item) {
                        $safeItem = $this->sanitizeLabel($item->instruction);
                        $mandatoryBadge = $item->is_mandatory ? ' *' : '';
                        $lines[] = "        Item_{$checklist->id}_{$item->id} : {$safeItem}{$mandatoryBadge}";
                    }
                }
            }

            if ($detail === 'full' && $task->raciAssignments->isNotEmpty()) {
                $raciSummary = $task->raciAssignments
                    ->map(fn ($r) => "{$r->role}")
                    ->implode(', ');
                $lines[] = "        RACI : {$raciSummary}";
            }

            if ($index < $process->tasks->count() - 1) {
                $nextTask = $process->tasks[$index + 1];
                $nextStateName = $this->sanitizeStateName($nextTask->name);
                $lines[] = "        Processing --> [*]";
                $lines[] = "    }";
                $lines[] = "    {$stateName} --> State_{$nextStateName}";
            } else {
                $lines[] = "        Processing --> [*]";
                $lines[] = "    }";
                $lines[] = "    {$stateName} --> End";
            }

            $lines[] = '';
        }

        $lines[] = '    End --> [*]';

        return implode("\n", $lines);
    }

    protected function sanitizeLabel(string $label): string
    {
        $label = str_replace('"', '\\"', $label);
        $label = str_replace("\n", ' ', $label);
        if (mb_strlen($label) > 80) {
            $label = mb_substr($label, 0, 77).'...';
        }
        return $label;
    }

    protected function sanitizeStateName(string $name): string
    {
        // State IDs must be valid Mermaid identifiers (no spaces, special chars)
        $name = preg_replace('/[^a-zA-Z0-9_]/', '_', $name);
        $name = preg_replace('/_+/', '_', $name);
        $name = trim($name, '_');
        if (mb_strlen($name) > 50) {
            $name = mb_substr($name, 0, 50);
        }
        return $name ?: 'unnamed';
    }
}
