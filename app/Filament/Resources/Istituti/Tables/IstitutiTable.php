<?php

namespace App\Filament\Resources\Istituti\Tables;

use App\Filament\Actions\ExcelExportAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class IstitutiTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->headerActions([ExcelExportAction::make()])
            ->columns([
                TextColumn::make('name')
                    ->label('Istituto')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('abi')
                    ->label('ABI')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                IconColumn::make('is_active')
                    ->label('Attivo')
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Attivo')->default(true),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make()->label('Portale'),
            ]);
    }
}
