<?php

namespace App\Filament\Resources\Clients\RelationManagers;

use App\Filament\Resources\BusinessFunctions\BusinessFunctionResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
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
                TextColumn::make('start_date')
                    ->label('Inizio')
                    ->date()
                    ->sortable(),
                TextColumn::make('end_date')
                    ->label('Fine')
                    ->date()
                    ->sortable(),
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
                        DatePicker::make('start_date')
                            ->label('Data Inizio')
                            ->default(now()),
                        DatePicker::make('end_date')
                            ->label('Data Fine'),
                        TextInput::make('temporary_reason')
                            ->label('Motivazione Temporanea'),
                    ]),
            ])
            ->actions([
                EditAction::make()
                    ->form(fn (EditAction $action): array => [
                        DatePicker::make('start_date')
                            ->label('Data Inizio'),
                        DatePicker::make('end_date')
                            ->label('Data Fine'),
                        TextInput::make('temporary_reason')
                            ->label('Motivazione Temporanea'),
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
