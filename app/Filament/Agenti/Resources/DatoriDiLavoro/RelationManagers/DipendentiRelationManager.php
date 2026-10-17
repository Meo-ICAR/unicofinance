<?php

namespace App\Filament\Agenti\Resources\DatoriDiLavoro\RelationManagers;

use App\Models\Client;
use App\Services\Agenti\ClientiDellAgente;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Organigramma del datore di lavoro limitato ai clienti del produttore: le persone che lavorano lì e che lui segue. */
class DipendentiRelationManager extends RelationManager
{
    protected static string $relationship = 'soci';

    protected static ?string $title = 'Organigramma';

    protected static ?string $modelLabel = 'Persona';

    protected static ?string $pluralModelLabel = 'Organigramma';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Client;
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('client_id', app(ClientiDellAgente::class)->ids())->with('client'))
            ->columns([
                TextColumn::make('role')->label('Ruolo')->badge(),
                TextColumn::make('client.name')->label('Cognome')->searchable(),
                TextColumn::make('client.first_name')->label('Nome')->placeholder('—'),
                TextColumn::make('client.tax_code')->label('Codice fiscale')->placeholder('—'),
            ]);
    }
}
