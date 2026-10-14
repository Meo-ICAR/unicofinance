<?php

namespace App\Filament\Resources\ListinoProvvigionales\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ListinoProvvigionaleInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('code')->label('Codice'),
                TextEntry::make('name')->label('Listino'),
                IconEntry::make('is_active')->label('Attivo')->boolean(),
            ]);
    }
}
