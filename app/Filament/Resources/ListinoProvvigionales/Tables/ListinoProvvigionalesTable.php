<?php

namespace App\Filament\Resources\ListinoProvvigionales\Tables;

use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Unico\Core\Models\TipoProdotto;

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
                TextColumn::make('tipoprodotto.name')
                    ->label('Prodotto'),
                IconColumn::make('coordinamento')
                    ->label('Coordinatore')
                    ->boolean(),
                TextColumn::make('value')
                    ->label('%')
                    ->suffix('%')
                    ->sortable(),
                TextColumn::make('assegnazioni_count')
                    ->label('Produttori')
                    ->counts('assegnazioni'),
                IconColumn::make('is_active')
                    ->label('Attivo')
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Attivo'),
                TernaryFilter::make('coordinamento')->label('Coordinatore'),
                SelectFilter::make('tipoprodotto_id')
                    ->label('Prodotto')
                    ->options(fn () => TipoProdotto::query()->orderBy('name')->pluck('name', 'id')),
            ])
            ->defaultSort('code')
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
