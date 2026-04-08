<?php

namespace App\Filament\Resources\ProcessTaskPrivacyData\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProcessTaskPrivacyDataTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('process_task_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('privacyDataType.name')
                    ->searchable(),
                TextColumn::make('access_level')
                    ->badge(),
                TextColumn::make('purpose')
                    ->searchable(),
                TextColumn::make('privacy_legal_base_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('retention_period')
                    ->searchable(),
                IconColumn::make('is_encrypted')
                    ->boolean(),
                IconColumn::make('is_shared_externally')
                    ->boolean(),
                TextColumn::make('created_by')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('updated_by')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
