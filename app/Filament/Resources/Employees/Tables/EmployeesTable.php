<?php

namespace App\Filament\Resources\Employees\Tables;

use App\Enums\EmployeeType;
use App\Filament\Resources\Employees\Schemas\EmployeeInfolist;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EmployeesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Dipendente')
                    ->description(fn ($record) => $record->role_title)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('employee_types')
                    ->label('Tipo Dipendente')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('department')
                    ->label('Dipartimento')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('branch.name')
                    ->label('Sede')
                    ->sortable(),

                TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable(),

                TextColumn::make('hiring_date')
                    ->label('Assunto il')
                    ->date('d/m/Y')
                    ->sortable(),

                IconColumn::make('is_structure')
                    ->label('Struttura')
                    ->boolean()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Creato il')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('employee_types')
                    ->label('Tipo Profilo')
                    ->options(EmployeeType::class),
                SelectFilter::make('company_branch_id')
                    ->label('Sede')
                    ->relationship('branch', 'name'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                ViewAction::make('stampa_nomina')
                    ->label('Visualizza Nomina')
                    ->icon('heroicon-o-document-text')
                    ->infolist(fn (Schema $infolist) => EmployeeInfolist::configure($infolist))
                    ->modalHeading('Anteprima Atto di Nomina')
                    ->modalWidth('5xl')
                    ->slideOver()
                    ->color('success')
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
