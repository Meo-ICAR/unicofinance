<?php

namespace App\Filament\Resources\Employees\RelationManagers;

use App\Filament\Resources\BusinessFunctions\BusinessFunctionResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Table;

class BusinessFunctionsRelationManager extends RelationManager
{
    protected static string $relationship = 'businessFunctions';

    protected static ?string $relatedResource = BusinessFunctionResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('macro_area')
                    ->badge()
                    ->searchable(),
                ToggleColumn::make('is_manager')
                    ->label('Responsabile'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                AttachAction::make()
                    ->multiple()
                    ->preloadRecordSelect()
                    ->form(fn (AttachAction $action): array => [
                        $action->getRecordSelect(),
                        Toggle::make('is_manager')
                            ->label('Responsabile'),
                    ]),
            ])
            ->actions([
                EditAction::make()
                    ->form(fn (EditAction $action): array => [
                        Toggle::make('is_manager')
                            ->label('Responsabile'),
                    ]),
                DetachAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                ]),
            ]);
    }
}
