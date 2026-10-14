<?php

namespace App\Filament\Resources\Istituti\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class IstitutoInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name')->label('Istituto'),
                TextEntry::make('abi')->label('ABI')->placeholder('-'),
                TextEntry::make('piva')->label('P. IVA')->placeholder('-'),
                TextEntry::make('email')->label('Email')->placeholder('-'),
                IconEntry::make('is_active')->label('Attivo')->boolean(),
            ]);
    }
}
