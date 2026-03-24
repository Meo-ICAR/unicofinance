<?php

namespace App\Filament\Resources\Companies\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CompaniesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('vat_number')
                    ->searchable(),
                TextColumn::make('vat_name')
                    ->searchable(),
                TextColumn::make('oam')
                    ->searchable(),
                TextColumn::make('oam_at')
                    ->date()
                    ->sortable(),
                TextColumn::make('oam_name')
                    ->searchable(),
                TextColumn::make('numero_iscrizione_rui')
                    ->searchable(),
                TextColumn::make('ivass')
                    ->searchable(),
                TextColumn::make('ivass_at')
                    ->date()
                    ->sortable(),
                TextColumn::make('ivass_name')
                    ->searchable(),
                TextColumn::make('ivass_section')
                    ->badge(),
                TextColumn::make('sponsor')
                    ->searchable(),
                TextColumn::make('company_type')
                    ->badge(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('smtp_host')
                    ->searchable(),
                TextColumn::make('smtp_port')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('smtp_username')
                    ->searchable(),
                TextColumn::make('smtp_encryption')
                    ->searchable(),
                TextColumn::make('smtp_from_email')
                    ->searchable(),
                TextColumn::make('smtp_from_name')
                    ->searchable(),
                IconColumn::make('smtp_enabled')
                    ->boolean(),
                IconColumn::make('smtp_verify_ssl')
                    ->boolean(),
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
