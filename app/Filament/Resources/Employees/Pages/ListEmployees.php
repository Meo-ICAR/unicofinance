<?php

namespace App\Filament\Resources\Employees\Pages;

use App\Filament\Resources\Employees\EmployeeResource;
use App\Models\Employee;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\ListRecords;

class ListEmployees extends ListRecords
{
    protected static string $resource = EmployeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('resignation')
                ->label('Dimissioni e Subentro')
                ->color('warning')
                ->icon('heroicon-o-user-minus')
                ->form([
                    Select::make('employee_id')
                        ->label('Dipendente')
                        ->options(Employee::query()->whereNull('deleted_at')->pluck('name', 'id'))
                        ->searchable()
                        ->required(),
                    DatePicker::make('termination_date')
                        ->label('Data Cessazione')
                        ->required()
                        ->default(now()),
                    Toggle::make('should_disable')
                        ->label('Disabilita Utente')
                        ->helperText('Se attivo, il dipendente verrà rimosso (soft-delete) con la data indicata.')
                        ->default(true),
                ])
                ->action(function (array $data) {
                    $employee = Employee::find($data['employee_id']);

                    if (! $employee) {
                        return;
                    }

                    $employee->update([
                        'termination_date' => $data['termination_date'],
                    ]);


                    if ($data['should_disable']) {
                        // Impostiamo deleted_at esattamente alla data di cessazione
                        // Filament/Laravel SoftDeletes usa il timestamp attuale se usiamo delete()
                        // Quindi impostiamo manualmente l'attributo.
                        $employee->deleted_at = $data['termination_date'];
                        $employee->save();
                        $employee->user->update([
                            'deleted_at' => $data['termination_date'],
                        ]);
                    }
                })
                ->modalHeading('Gestione Dimissioni e Subentro')
                ->modalDescription('Registra la cessazione del rapporto di lavoro.')
                ->modalSubmitActionLabel('Registra Dimissioni'),
        ];
    }
}
