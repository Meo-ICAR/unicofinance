<?php

namespace App\Filament\RelationManagers;

use App\Models\Pratica;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Impegni di terzi associati al codice fiscale o alla partita IVA della pratica. */
class ImpegniTerziRelationManager extends RelationManager
{
    protected static string $relationship = 'impegniTerzi';

    protected static ?string $title = 'Impegni di terzi';

    protected static ?string $modelLabel = 'Impegno';

    protected static ?string $pluralModelLabel = 'Impegni di terzi';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Pratica;
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('codice_pratica')
            ->modifyQueryUsing(fn ($query) => $query->with('tipoprodotto'))
            ->columns([
                TextColumn::make('tipoprodotto.name')->label('Tipo')->badge(),
                TextColumn::make('denominazione_banca')->label('Banca / ente')->searchable(),
                TextColumn::make('denominazione_prodotto')->label('Prodotto'),
                TextColumn::make('rata')->label('Rata')->money('EUR'),
                TextColumn::make('nrate')->label('Numero rate'),
                TextColumn::make('amount')->label('Importo')->money('EUR'),
                TextColumn::make('data_inserimento_pratica')->label('Decorrenza')->date('d/m/Y')->sortable(),
            ])
            ->defaultSort('data_inserimento_pratica', 'desc');
    }
}
