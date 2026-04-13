<?php

use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\Client;
use App\Models\Company;
use App\Models\Process;
use App\Models\ProcessTask;
use App\Models\TaskExecution;

/**
 * Create a complete BPM process template with tasks, checklists, and items
 *
 * @return Process
 */
function createBpmProcess(array $options = []): Process
{
    $company = $options['company'] ?? Company::factory()->create();
    $taskCount = $options['task_count'] ?? 1;
    $checklistsPerTask = $options['checklists_per_task'] ?? 1;
    $itemsPerChecklist = $options['items_per_checklist'] ?? 1;

    $process = Process::factory()->create([
        'company_id' => $company->id,
        'is_active' => $options['is_active'] ?? true,
        'version' => $options['version'] ?? 1,
        'target_model' => $options['target_model'] ?? Client::class,
        'name' => $options['name'] ?? 'Test Process',
        'code' => $options['code'] ?? null,
    ]);

    for ($t = 0; $t < $taskCount; $t++) {
        $task = ProcessTask::factory()->create([
            'company_id' => $company->id,
            'process_id' => $process->id,
            'sequence_number' => $t + 1,
        ]);

        for ($c = 0; $c < $checklistsPerTask; $c++) {
            $checklist = Checklist::factory()->create([
                'process_task_id' => $task->id,
            ]);

            for ($i = 0; $i < $itemsPerChecklist; $i++) {
                ChecklistItem::factory()->create([
                    'checklist_id' => $checklist->id,
                ]);
            }
        }
    }

    return $process;
}

/**
 * Create a task execution for a client with cloned checklist items
 *
 * @return TaskExecution
 */
function createTaskExecution(ProcessTask $task, Client $client, array $options = []): TaskExecution
{
    return TaskExecution::create([
        'process_task_id' => $task->id,
        'client_id' => $client->id,
        'status' => $options['status'] ?? 'todo',
        'target_type' => Client::class,
        'target_id' => $client->id,
    ]);
}

/**
 * Create a complete BPM setup with process, tasks, and a client ready for execution
 *
 * @return array{company: Company, process: Process, tasks: \Illuminate\Support\Collection, client: Client}
 */
function createBpmSetup(array $options = []): array
{
    $company = $options['company'] ?? Company::factory()->create();
    $client = $options['client'] ?? Client::factory()->create([
        'company_id' => $company->id,
    ]);

    $process = createBpmProcess(array_merge($options, [
        'company' => $company,
    ]));

    return [
        'company' => $company,
        'process' => $process,
        'tasks' => $process->tasks,
        'client' => $client,
    ];
}
