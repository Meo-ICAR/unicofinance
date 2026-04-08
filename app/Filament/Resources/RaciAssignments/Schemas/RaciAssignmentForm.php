<?php

namespace App\Filament\Resources\RaciAssignments\Schemas;

use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;

class RaciAssignmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dettagli Assegnazione RACI')
                ->description('Assegna una funzione aziendale a un task specifico con un ruolo definito.')
                ->columns(2)
                ->schema([

                    Select::make('process_task_id')
                        ->relationship('processTask', 'name')
                        ->disabled()
                        ->helperText(fn ($get) => $get('process_task_id')
                            ? "Appartiene al processo: " . \App\Models\ProcessTask::find($get('process_task_id'))?->process?->name
                            : null)
                        ->searchable()
                        ->preload()
                        ->required(),

                    Select::make('business_function_id')
                        ->label('Funzione Aziendale')
                        ->relationship('businessFunction', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),

                    Select::make('role')
                        ->label('Ruolo RACI')
                        ->options([
                            'R' => 'Responsible (Esegue)',
                            'A' => 'Accountable (Responsabile)',
                            'C' => 'Consulted (Consultato)',
                            'I' => 'Informed (Informato)',
                        ])
                        ->required()
                        ->native(false),
                ]),
                 ]);
    }
}
