<?php

namespace App\Filament\Resources\ListinoProvvigionales\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Unico\Core\Models\TipoProdotto;

class ProdottiRelationManager extends RelationManager
{
    protected static string $relationship = 'righe';

    protected static ?string $title = 'Prodotti';

    protected static ?string $modelLabel = 'Prodotto';

    protected static ?string $pluralModelLabel = 'Prodotti';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('tipoprodotto_id')
                    ->label('Prodotto')
                    ->options(fn () => TipoProdotto::query()->orderBy('tipo_prodotto')->pluck('name', 'id'))
                    ->searchable()
                    ->required(),
                TextInput::make('value')
                    ->label('Percentuale')
                    ->numeric()
                    ->suffix('%')
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('tipoprodotto.name')->label('Prodotto'),
                TextColumn::make('tipoprodotto.tipo_provvigioni')
                    ->label('Base di calcolo')
                    ->placeholder('Non definita'),
                TextColumn::make('value')->label('Percentuale')->suffix('%'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
