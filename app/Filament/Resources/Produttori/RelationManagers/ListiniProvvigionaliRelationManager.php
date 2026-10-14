<?php

namespace App\Filament\Resources\Produttori\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ListiniProvvigionaliRelationManager extends RelationManager
{
    protected static string $relationship = 'listiniProvvigionali';

    protected static ?string $title = 'Listini';

    protected static ?string $modelLabel = 'Listino';

    protected static ?string $pluralModelLabel = 'Listini';

    /** Il resource Produttori è in sola lettura, ma i listini si gestiscono da qui. */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            // Il filtro sui già assegnati è nella query delle opzioni: l'esclusione di Filament sarebbe una subquery fra database.
            ->allowDuplicates()
            ->columns([
                TextColumn::make('code')->label('Codice')->sortable(),
                TextColumn::make('name')->label('Listino')->searchable(),
                TextColumn::make('tipoprodotto.name')->label('Prodotto'),
                TextColumn::make('coordinamento')
                    ->label('Ruolo')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Coordinatore' : 'Diretto')
                    ->color(fn (bool $state): string => $state ? 'warning' : 'success'),
                TextColumn::make('value')->label('%')->suffix('%'),
            ])
            ->defaultSort('code')
            ->headerActions([
                AttachAction::make()
                    ->preloadRecordSelect()
                    ->recordSelectOptionsQuery(function (Builder $query): Builder {
                        // Un fornitore ha un solo listino per prodotto e ruolo.
                        return $query->whereNotExists(fn ($sub) => $sub
                            ->selectRaw('1')
                            ->from('fornitori_listini_provvigionali as fl')
                            ->join('listini_provvigionali as l', 'l.id', '=', 'fl.listino_provvigionale_id')
                            ->where('fl.fornitori_id', $this->getOwnerRecord()->getKey())
                            ->whereColumn('l.tipoprodotto_id', 'listini_provvigionali.tipoprodotto_id')
                            ->whereColumn('l.coordinamento', 'listini_provvigionali.coordinamento'));
                    })
                    ->recordSelectSearchColumns(['code', 'name']),
            ])
            ->recordActions([
                DetachAction::make(),
            ]);
    }
}
