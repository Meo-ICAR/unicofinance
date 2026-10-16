<?php

namespace App\Filament\Resources\ProvvigioniAttive\Tables;

use App\Filament\Resources\ProvvigioniAttive\Schemas\ProvvigioneAttivaForm;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Unico\Core\Models\Cliente;
use Unico\Core\Models\ProvvigioniRule;
use Unico\Core\Models\TipoProdotto;

class ProvvigioniAttiveTable
{
    /** @param  bool  $conBanca  false nell'elenco di una banca: colonna e filtro della banca non servono */
    public static function configure(Table $table, bool $conBanca = true): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['tipoprodotto', 'clienti']))
            ->columns([
                TextColumn::make('tipoprodotto.name')->label('Prodotto')->placeholder('Tutti i prodotti')->sortable(),
                TextColumn::make('clienti.name')->label('Banca')->placeholder('Tutte le banche')->sortable()->searchable()->visible($conBanca),
                TextColumn::make('tipo_provvigioni')
                    ->label('Base')
                    ->formatStateUsing(fn (?string $state): string => ProvvigioneAttivaForm::BASI[$state] ?? (string) $state)
                    ->badge(),
                TextColumn::make('value')
                    ->label('Valore')
                    ->state(fn (ProvvigioniRule $record): string => self::valore($record))
                    ->alignEnd(),
                TextColumn::make('fascia_ltv')
                    ->label('LTV')
                    ->state(fn (ProvvigioniRule $record): ?string => $record->ltv_min === null && $record->ltv_max === null ? null : ((float) $record->ltv_min).'–'.((float) ($record->ltv_max ?? 100)).'%')
                    ->placeholder('—'),
                TextColumn::make('valid_from')->label('Dal')->date('d/m/Y')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('valid_to')->label('Al')->date('d/m/Y')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('notes')->label('Note')->limit(60)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters(array_values(array_filter([
                SelectFilter::make('tipoprodotto_id')
                    ->label('Tipo prodotto')
                    ->options(fn (): array => TipoProdotto::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable(),
                $conBanca ? SelectFilter::make('clienti_id')
                    ->label('Banca')
                    ->options(fn (): array => Cliente::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable() : null,
            ])))
            ->defaultSort('tipoprodotto_id')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    private static function valore(ProvvigioniRule $regola): string
    {
        if ($regola->value === null) {
            return 'Libera';
        }

        $numero = rtrim(rtrim(number_format((float) $regola->value, 2, ',', '.'), '0'), ',');

        return $regola->tipo_provvigioni === 'fixed' ? "{$numero} €" : "{$numero}%";
    }
}
