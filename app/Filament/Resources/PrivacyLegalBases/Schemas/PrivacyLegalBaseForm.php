<?php

namespace App\Filament\Resources\PrivacyLegalBases\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class PrivacyLegalBaseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('reference_article')
                    ->required()
                    ->default('Art. 6 par. 1 lett. ...'),
                Textarea::make('description')
                    ->columnSpanFull(),
            ]);
    }
}
