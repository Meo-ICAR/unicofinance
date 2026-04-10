<?php

namespace App\Filament\Resources\TaskExecutions\Pages;

use App\Filament\Resources\TaskExecutions\TaskExecutionResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Http\Request;

class CreateTaskExecution extends CreateRecord
{
    protected static string $resource = TaskExecutionResource::class;

    public function mount(int|string|null $process_id = null, int|string|null $task_id = null, int|string|null $request_registry_id = null): void
    {
        parent::mount();

        // Pre-popolamento dai parametri URL
        if ($process_id) {
            $this->form->fill([
                'process_task_id' => $task_id,
                'request_registry_id' => $request_registry_id,
            ]);
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Assicura che process_task_id sia impostato
        if (!isset($data['process_task_id'])) {
            $request = request();
            $data['process_task_id'] = $request->get('task_id');
            $data['request_registry_id'] = $request->get('request_registry_id');
        }

        return $data;
    }
}
