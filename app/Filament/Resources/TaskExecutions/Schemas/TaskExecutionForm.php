<?php

namespace App\Filament\Resources\TaskExecutions\Schemas;

use App\Services\BpmEngineService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class TaskExecutionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('process_task_id')
                    ->relationship('processTask', 'name')
                    ->required(),
                Select::make('employee_id')
                    ->relationship('employee', 'name'),
                Select::make('client_id')
                    ->relationship('client', 'name'),
                DatePicker::make('due_date'),
                DateTimePicker::make('started_at'),
                DateTimePicker::make('completed_at'),
                TextInput::make('reference_number'),
                TextInput::make('audit_dms_id'),
                TextInput::make('status')
                    ->required()
                    ->default('todo'),
                // Dropdown per le azioni disponibili
                Select::make('action')
                    ->label('Azione')
                    ->options(function () {
                        // Ottieni le azioni disponibili dal registry
                        $actions = BpmEngineService::getAvailableActions();
                        return collect($actions)->mapWithKeys(function ($action) {
                            return [$action['id'] => $action['label']];
                        })->toArray();
                    })
                    ->searchable()
                    ->required()
                    ->default(null),
                Section::make('Checklist Dinamica')
                    ->description('Istruzioni generate in base al profilo cliente e alle regole di compliance.')
                    ->schema([
                        Placeholder::make('checklist_display')
                            ->content(function (TaskExecution $record, BpmEngineService $engine) {
                                $items = $engine->getEvaluatedChecklist($record);

                                if ($items->isEmpty())
                                    return 'Nessun controllo richiesto.';

                                // Genera una lista HTML semplice per il placeholder
                                $html = '<ul class="list-disc ml-5">';
                                foreach ($items as $item) {
                                    $badge = $item->is_mandatory
                                        ? '<span class="text-danger-600 font-bold">[OBBLIGATORIO]</span>'
                                        : '<span class="text-gray-500">[Opzionale]</span>';
                                    $html .= "<li>{$badge} {$item->instruction}</li>";
                                }
                                $html .= '</ul>';

                                return new HtmlString($html);
                            })
                    ]),
                TextInput::make('previous_task_execution_id')
                    ->numeric(),
                TextInput::make('created_by')
                    ->numeric(),
                TextInput::make('updated_by')
                    ->numeric(),
                TextInput::make('deleted_by')
                    ->numeric(),
            ]);
    }
}
