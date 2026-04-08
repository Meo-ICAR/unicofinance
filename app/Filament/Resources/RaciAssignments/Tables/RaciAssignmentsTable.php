<?php

namespace App\Filament\Resources\RaciAssignments\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\ReplicateAction;
use App\Models\RaciAssignment;
use App\Filament\Resources\RaciAssignments\RaciAssignmentResource;



class RaciAssignmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
               TextColumn::make('processTask.process.name')
                ->label('Processo')
                ->description(fn ($record) => "Task: " . $record->processTask->name)
                ->sortable()
                ->searchable(),

            TextColumn::make('businessFunction.name')
                ->label('Funzione')
                ->sortable()
                ->searchable(),

            TextColumn::make('role')
                ->label('Ruolo')
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    'R' => 'info',     // Blu
                    'A' => 'danger',   // Rosso (critico)
                    'C' => 'warning',  // Giallo
                    'I' => 'success',  // Verde
                })
                ->formatStateUsing(fn (string $state): string => $state)
                ->sortable(),


        ])
        ->filters([
            SelectFilter::make('role')
                ->label('Filtra per Ruolo')
                ->options([
                    'R' => 'Responsible',
                    'A' => 'Accountable',
                    'C' => 'Consulted',
                    'I' => 'Informed',
                ]),

            SelectFilter::make('business_function_id')
                ->label('Funzione Aziendale')
                ->relationship('businessFunction', 'name')
                ->searchable()
                ->preload(),
        ])
            ->recordActions([
                EditAction::make(),

            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
