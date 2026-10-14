<?php

namespace App\Filament\RelationManagers;

use App\Filament\Agenti\Resources\Pratiche\PraticaResource;
use App\Models\Pratica;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Altre pratiche dello stesso richiedente. */
class PraticheRelationManager extends RelationManager
{
    protected static string $relationship = 'praticheRichiedente';

    protected static ?string $title = 'Pratiche';

    protected static ?string $modelLabel = 'Pratica';

    protected static ?string $pluralModelLabel = 'Pratiche';

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
            ->columns([
                TextColumn::make('codice_pratica')->label('Codice'),
                TextColumn::make('cognome_cliente')->label('Cognome'),
                TextColumn::make('nome_cliente')->label('Nome'),
                TextColumn::make('praticaStato.codice')->label('Stato')->badge(),
                TextColumn::make('amount')->label('Importo')->money('EUR'),
                TextColumn::make('data_inserimento_pratica')->label('Inserita il')->date('d/m/Y'),
            ])
            ->recordUrl(fn (Pratica $record): string => PraticaResource::getUrl('edit', ['record' => $record]));
    }
}
