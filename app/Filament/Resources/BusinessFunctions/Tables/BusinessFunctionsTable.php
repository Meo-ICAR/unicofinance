<?php

namespace App\Filament\Resources\BusinessFunctions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BusinessFunctionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->searchable(),
                TextColumn::make('macro_area')
                    ->badge()
                    ->searchable(),
                TextColumn::make('name')
                    ->label('Nome Funzione')
                    ->searchable(),
                TextColumn::make('employees.name')
                    ->label('Dipendenti')
                    ->badge()
                    ->searchable(),
                TextColumn::make('type')
                    ->badge()
                    ->searchable(),
                TextColumn::make('outsourcable_status')
                    ->badge()
                    ->searchable(),
                TextColumn::make('manager.code')
                    ->searchable(),
            ])
            ->filters([
                //
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
