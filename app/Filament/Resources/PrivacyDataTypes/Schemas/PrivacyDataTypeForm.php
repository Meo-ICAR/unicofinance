<?php

namespace App\Filament\Resources\PrivacyDataTypes\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PrivacyDataTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('slug')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                Select::make('category')
                    ->options(['comuni' => 'Comuni', 'particolari' => 'Particolari', 'giudiziari' => 'Giudiziari'])
                    ->required(),
                TextInput::make('retention_years')
                    ->required()
                    ->numeric()
                    ->default(10),
                TextInput::make('created_by')
                    ->numeric(),
                TextInput::make('updated_by')
                    ->numeric(),
            ]);
    }
}
