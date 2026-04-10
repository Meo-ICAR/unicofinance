<?php

namespace App\Filament\Actions;

use App\Models\Process;
use App\Models\TaskExecution;
use Filament\Forms\Components\Select;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification as SystemNotification;
use Filament\Notifications\Notification as FilamentNotification;
use App\Notifications\TaskAssignedNotification;

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

                        return Filament::getTenant()->processes()->where('is_active', true)
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
                DB::transaction(function () use ($record, $data) {
                    // Il bello di Model $record è che accetta QUALSIASI modello (Employee, Client, ecc.)
    
                    $process = Process::with(['tasks.raciAssignments'])->find($data['process_id']);
                    $taskCount = 0;

                    foreach ($process->tasks as $task) {
                        $responsible = $task->raciAssignments->where('raci_role', 'R')->first();

                        $execution = TaskExecution::create([
                            'process_task_id' => $task->id,
                            'employee_id' => $responsible?->employee_id,
                            'client_id' => $responsible?->client_id,
                            'status' => 'todo',
                            // target_type/target_id derived from the Process template:
                            // Process.target_model determines which model this execution targets
                            'target_type' => $process->target_model,
                            'target_id' => $record->getKey(),
                        ]);

                        // --- GESTIONE NOTIFICHE (RIPRISTINATA) ---
    
                        // CASO 1: Dipendente Interno (Ha un account User collegato)
                        if ($execution->employee && $execution->employee->user) {
                            // Invia Email + Campanellina in Filament
                            $execution->employee->user->notify(new TaskAssignedNotification($execution));
                        }
                        // CASO 2: Consulente (Ha un account User collegato)
                        elseif ($execution->client && $execution->client->user) {
                            $execution->client->user->notify(new TaskAssignedNotification($execution));
                        }
                        // CASO 3: Destinatario Esterno Volante (es. se nel Target c'è una mail o se vogliamo avvisare un cliente specifico)
                        // Usiamo il routing "On-Demand" di Laravel. Non serve che questa persona esista nel database users!
                        elseif (method_exists($record, 'getMorphClass') && $record->getMorphClass() === 'App\Models\Customer' && $record->email) {
                            // Invia SOLO l'email a questo indirizzo
                            SystemNotification::route('mail', $record->email)
                                ->notify(new TaskAssignedNotification($execution));
                        }

                        $taskCount++;
                    }

                    FilamentNotification::make()
                        ->success()
                        ->title('Processo Avviato')
                        ->body("Sono stati generati e assegnati {$taskCount} task operativi.")
                        ->send();
                });
            });
    }
}
