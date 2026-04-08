<?php

namespace App\Filament\Resources\ProcessTasks\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class ProcessTaskForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
                Select::make('business_function_id')
                    ->relationship('businessFunction', 'name')
                    ->required(),
                TextInput::make('sequence_number')
                    ->required()
                    ->numeric()
                    ->default(0),

            ]);
    }
}
