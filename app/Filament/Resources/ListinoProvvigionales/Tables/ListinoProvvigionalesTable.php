<?php

namespace App\Filament\Resources\ListinoProvvigionales\Tables;

use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ListinoProvvigionalesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Codice')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Listino')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('righe_count')
                    ->label('Prodotti')
                    ->counts('righe'),
                TextColumn::make('fornitori_count')
                    ->label('Produttori')
                    ->counts('fornitori'),
                IconColumn::make('is_active')
                    ->label('Attivo')
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Attivo'),
            ])
            ->defaultSort('code')
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
