<?php
namespace App\Filament\Actions;

use App\Models\Process;
use App\Models\TaskExecution;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Model;

class StartProcessAction extends Action
{
    // Il metodo setUp() viene chiamato automaticamente quando l'azione viene instanziata
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->name('start_process')  // Il nome interno dell'azione
            ->label('Avvia Processo')
            ->icon('heroicon-o-play')
            ->color('success')
            ->modalHeading('Avvia un nuovo processo')
            ->modalDescription('Scegli quale procedura avviare. I task verranno assegnati in automatico in base alle regole RACI.')
            ->form([
                Select::make('process_id')
                    ->label('Seleziona il Processo')
                    // Ottimizzazione: puoi filtrare solo i processi attivi
                    ->options(function (Model $record) {
                        // $record contiene l'entità da cui stiamo cliccando (es. l'Employee)
                        $currentModelClass = $record->getMorphClass();

                        return Process::where('is_active', true)
                            ->where(function ($query) use ($currentModelClass) {
                                // Mostra i processi specifici per questo modello...
                                $query
                                    ->where('target_model', $currentModelClass)
                                    // ...oppure i processi generici (senza target specifico)
                                    ->orWhereNull('target_model');
                            })
                            ->pluck('name', 'id');
                    })
                    ->required()
                    ->searchable(),
            ])
            ->action(function (Model $record, array $data): void {
                // Il bello di Model $record è che accetta QUALSIASI modello (Employee, Client, ecc.)

                $process = Process::with(['tasks.raciAssignments'])->find($data['process_id']);
                $taskCount = 0;

                foreach ($process->tasks as $task) {
                    $responsible = $task->raciAssignments->where('raci_role', 'R')->first();

                    TaskExecution::create([
                        'process_task_id' => $task->id,
                        'employee_id' => $responsible?->employee_id,
                        'client_id' => $responsible?->client_id,
                        'status' => 'todo',
                        // MAGIA POLIMORFICA:
                        // getMorphClass() capisce da solo se è 'App\Models\Employee' o altro
                        // getKey() prende l'ID corretto
                        'target_type' => $record->getMorphClass(),
                        'target_id' => $record->getKey(),
                    ]);

                    $taskCount++;
                }

                Notification::make()
                    ->success()
                    ->title('Processo Avviato')
                    ->body("Sono stati generati e assegnati {$taskCount} task operativi.")
                    ->send();
            });
    }
}
