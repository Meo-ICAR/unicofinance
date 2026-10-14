<?php

namespace App\Filament\Resources\ListinoProvvigionales\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;
use Unico\Core\Models\TipoProdotto;

class ListinoProvvigionaleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('Codice')
                    ->required()
                    ->maxLength(20)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get) => $rule
                        ->where('coordinamento', (bool) $get('coordinamento'))),
                TextInput::make('name')
                    ->label('Listino')
                    ->required()
                    ->maxLength(100),
                Select::make('tipoprodotto_id')
                    ->label('Prodotto')
                    ->options(fn () => TipoProdotto::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->required(),
                Toggle::make('coordinamento')
                    ->label('Come coordinatore')
                    ->helperText('Per le pratiche fatte da fornitori coordinati dal produttore.')
                    ->live(),
                TextInput::make('value')
                    ->label('Percentuale')
                    ->numeric()
                    ->suffix('%')
                    ->required(),
                Toggle::make('is_active')
                    ->label('Attivo')
                    ->default(true),
            ]);
    }
}
