<?php

namespace App\Filament\Resources\ListinoProvvigionales\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

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
                    ->unique(ignoreRecord: true),
                TextInput::make('name')
                    ->label('Listino')
                    ->required()
                    ->maxLength(100),
                Toggle::make('is_active')
                    ->label('Attivo')
                    ->default(true),
            ]);
    }
}
