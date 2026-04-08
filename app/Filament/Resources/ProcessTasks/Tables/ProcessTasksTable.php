<?php

namespace App\Filament\Resources\ProcessTasks\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Forms\Components\Select;
use \App\Models\BusinessFunction;
use \App\Models\ProcessTask;

class ProcessTasksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('businessFunction.name')
                    ->searchable(),
                TextColumn::make('raci_r')
                    ->label('R')
                    ->getStateUsing(fn ($record) => $record->raciAssignments->where('role', 'R')->first()?->businessFunction?->name ?? '-')
                    ->badge()
                    ->color('info')
                    ->action(
                        Action::make('change_r')
                            ->modalHeading('Modifica Ruolo: R')
                            ->modalWidth('sm')
                            ->form([
                                Select::make('business_function_id')
                                    ->label('Funzione Aziendale')
                                    ->options(function (ProcessTask $record) {
                                        $usedFunctionIds = $record->raciAssignments()
                                            ->where('role', '!=', 'R')
                                            ->pluck('business_function_id')
                                            ->filter()
                                            ->toArray();

                                        return BusinessFunction::where('company_id', auth()->user()?->current_company_id)
                                            ->whereNotIn('id', $usedFunctionIds)
                                            ->pluck('name', 'id');
                                    })
                                    ->searchable()
                                    ->required()
                            ])
                            ->fillForm(fn ($record) => [
                                'business_function_id' => $record->raciAssignments->where('role', 'R')->first()?->business_function_id
                            ])
                            ->action(function ($record, array $data) {
                                $record->raciAssignments()->updateOrCreate(
                                    ['role' => 'R', 'company_id' => $record->company_id],
                                    ['business_function_id' => $data['business_function_id']]
                                );
                            })
                    ),
                TextColumn::make('raci_a')
                    ->label('A')
                    ->getStateUsing(fn ($record) => $record->raciAssignments->where('role', 'A')->first()?->businessFunction?->name ?? '-')
                    ->badge()
                    ->color('danger')
                    ->action(
                        Action::make('change_a')
                            ->modalHeading('Modifica Ruolo: A')
                            ->modalWidth('sm')
                            ->form([
                                Select::make('business_function_id')
                                    ->label('Funzione Aziendale')
                                    ->options(function (ProcessTask $record) {
                                        $usedFunctionIds = $record->raciAssignments()
                                            ->where('role', '!=', 'A')
                                            ->pluck('business_function_id')
                                            ->filter()
                                            ->toArray();

                                        return BusinessFunction::where('company_id', auth()->user()?->current_company_id)
                                            ->whereNotIn('id', $usedFunctionIds)
                                            ->pluck('name', 'id');
                                    })
                                    ->searchable()
                                    ->required()
                            ])
                            ->fillForm(fn ($record) => [
                                'business_function_id' => $record->raciAssignments->where('role', 'A')->first()?->business_function_id
                            ])
                            ->action(function ($record, array $data) {
                                $record->raciAssignments()->updateOrCreate(
                                    ['role' => 'A', 'company_id' => $record->company_id],
                                    ['business_function_id' => $data['business_function_id']]
                                );
                            })
                    ),
                TextColumn::make('raci_c')
                    ->label('C')
                    ->getStateUsing(fn ($record) => $record->raciAssignments->where('role', 'C')->first()?->businessFunction?->name ?? '-')
                    ->badge()
                    ->color('warning')
                    ->action(
                        Action::make('change_c')
                            ->modalHeading('Modifica Ruolo: C')
                            ->modalWidth('sm')
                            ->form([
                                Select::make('business_function_id')
                                    ->label('Funzione Aziendale')
                                    ->options(function (ProcessTask $record) {
                                        $usedFunctionIds = $record->raciAssignments()
                                            ->where('role', '!=', 'C')
                                            ->pluck('business_function_id')
                                            ->filter()
                                            ->toArray();

                                        return BusinessFunction::where('company_id', auth()->user()?->current_company_id)
                                            ->whereNotIn('id', $usedFunctionIds)
                                            ->pluck('name', 'id');
                                    })
                                    ->searchable()
                                    ->required()
                            ])
                            ->fillForm(fn ($record) => [
                                'business_function_id' => $record->raciAssignments->where('role', 'C')->first()?->business_function_id
                            ])
                            ->action(function ($record, array $data) {
                                $record->raciAssignments()->updateOrCreate(
                                    ['role' => 'C', 'company_id' => $record->company_id],
                                    ['business_function_id' => $data['business_function_id']]
                                );
                            })
                    ),
                TextColumn::make('raci_i')
                    ->label('I')
                    ->getStateUsing(fn ($record) => $record->raciAssignments->where('role', 'I')->first()?->businessFunction?->name ?? '-')
                    ->badge()
                    ->color('success')
                    ->action(
                        Action::make('change_i')
                            ->modalHeading('Modifica Ruolo: I')
                            ->modalWidth('sm')
                            ->form([
                                Select::make('business_function_id')
                                    ->label('Funzione Aziendale')
                                    ->options(function (ProcessTask $record) {
                                        $usedFunctionIds = $record->raciAssignments()
                                            ->where('role', '!=', 'I')
                                            ->pluck('business_function_id')
                                            ->filter()
                                            ->toArray();

                                        return BusinessFunction::where('company_id', auth()->user()?->current_company_id)
                                            ->whereNotIn('id', $usedFunctionIds)
                                            ->pluck('name', 'id');
                                    })
                                    ->searchable()
                                    ->required()
                            ])
                            ->fillForm(fn ($record) => [
                                'business_function_id' => $record->raciAssignments->where('role', 'I')->first()?->business_function_id
                            ])
                            ->action(function ($record, array $data) {
                                $record->raciAssignments()->updateOrCreate(
                                    ['role' => 'I', 'company_id' => $record->company_id],
                                    ['business_function_id' => $data['business_function_id']]
                                );
                            })
                    ),

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
