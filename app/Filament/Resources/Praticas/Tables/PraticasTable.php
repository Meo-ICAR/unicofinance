<?php

namespace App\Filament\Resources\Praticas\Tables;

use App\Filament\Resources\Praticas\PraticaResource;
use App\Models\Pratica;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PraticasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('denominazione_banca')
                    ->label('Banca')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tipoprodotto.name')
                    ->label('Tipo prodotto')
                    ->sortable(),
                TextColumn::make('praticaStato.name')
                    ->label('Stato pratica')
                    ->badge()
                    ->sortable(),
                TextColumn::make('cognome_cliente')
                    ->label('Cognome cliente')
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('denominazione_banca')
                    ->label('Banca')
                    ->options(fn (): array => Pratica::query()
                        ->whereNotNull('denominazione_banca')
                        ->distinct()
                        ->orderBy('denominazione_banca')
                        ->pluck('denominazione_banca', 'denominazione_banca')
                        ->all())
                    ->searchable(),
                SelectFilter::make('fornitori_id')
                    ->label('Agente')
                    ->relationship('fornitori', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('pratica_stato_id')
                    ->label('Stato pratica')
                    ->relationship('praticaStato', 'name')
                    ->searchable()
                    ->preload(),
                Filter::make('ultimo_aggiornamento')
                    ->label('Ultimo aggiornamento')
                    ->schema([
                        DatePicker::make('dal')->label('Aggiornata dal'),
                        DatePicker::make('al')->label('Aggiornata fino al'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['dal'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('updated_at', '>=', $date))
                        ->when($data['al'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('updated_at', '<=', $date))),
                Filter::make('escludi_respinte')
                    ->label('Escludi pratiche respinte')
                    ->toggle()
                    ->default()
                    ->query(fn (Builder $query): Builder => $query->whereNull('rejected_at')),
            ])
            ->defaultSort('updated_at', 'desc')
            ->recordUrl(fn (Pratica $record): string => PraticaResource::getUrl('edit', ['record' => $record]));
    }
}
