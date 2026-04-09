<?php

namespace App\Filament\Resources\BusinessFunctions\Pages;

use App\Filament\Resources\BusinessFunctions\BusinessFunctionResource;
use App\Models\Employee;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditBusinessFunction extends EditRecord
{
    protected static string $resource = BusinessFunctionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            Action::make('subentro')
                ->label('Subentro')
                ->color('info')
                ->icon('heroicon-o-arrows-right-left')
                ->form([
                    Select::make('outgoing_employee_id')
                        ->label('Dipendente da Sostituire')
                        ->placeholder('Seleziona il dipendente uscente (anche dimissionari)')
                        ->options(fn () => $this->getRecord()->employees()->withTrashed()->pluck('name', 'employees.id'))
                        ->searchable()
                        ->required(),
                    Select::make('incoming_employee_id')
                        ->label('Nuovo Dipendente (Subentrante)')
                        ->options(fn () => Employee::query()->whereNull('deleted_at')->pluck('name', 'id'))
                        ->searchable()
                        ->required(),
                    Toggle::make('is_manager')
                        ->label('Mantieni ruolo di Responsabile')
                        ->default(true),
                ])
                ->action(function (array $data) {
                    $businessFunction = $this->getRecord();
                    $replacedId = $data['outgoing_employee_id'];
                    $newId = $data['incoming_employee_id'];

                    // Detach replaced (se ancora presente)
                    $businessFunction->employees()->detach($replacedId);

                    // Attach new
                    $businessFunction->employees()->attach($newId, [
                        'is_manager' => $data['is_manager'],
                    ]);

                    Notification::make()
                        ->title('Subentro completato con successo')
                        ->success()
                        ->send();
                })
                ->modalHeading('Procedura di Subentro')
                ->modalDescription('Sostituisci un dipendente all\'interno di questa funzione aziendale.')
                ->modalSubmitActionLabel('Esegui Subentro'),
        ];
    }
}
