<?php

namespace App\Filament\Resources\ListinoProvvigionales\RelationManagers;

use App\Models\ListinoProvvigionale;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FornitoriRelationManager extends RelationManager
{
    protected static string $relationship = 'fornitori';

    protected static ?string $title = 'Produttori';

    protected static ?string $modelLabel = 'Produttore';

    protected static ?string $pluralModelLabel = 'Produttori';

    /** Nella pagina di dettaglio le relation manager sono in sola lettura per default. */
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
                TextColumn::make('name')->label('Produttore')->searchable(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->preloadRecordSelect()
                    ->recordSelectOptionsQuery(function (Builder $query): Builder {
                        /** @var ListinoProvvigionale $listino */
                        $listino = $this->getOwnerRecord();

                        $listini = (new ListinoProvvigionale)->getConnection()->getDatabaseName().'.listini_provvigionali';

                        // Un fornitore ha un solo listino per prodotto e ruolo.
                        return $query->whereNotIn('fornitoris.id', fn ($sub) => $sub
                            ->select('fl.fornitori_id')
                            ->from(ListinoProvvigionale::assegnazioniTable().' as fl')
                            ->join($listini.' as l', 'l.id', '=', 'fl.listino_provvigionale_id')
                            ->where('l.tipoprodotto_id', $listino->tipoprodotto_id)
                            ->where('l.coordinamento', $listino->coordinamento));
                    }),
            ])
            ->recordActions([
                DetachAction::make(),
            ]);
    }
}
