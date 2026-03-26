<?php

namespace App\Filament\Resources\BusinessFunctions\Schemas;

use App\Enums\BusinessFunctionType;
use App\Enums\MacroArea;
use App\Enums\OutsourcableStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class BusinessFunctionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required(),
                Select::make('macro_area')
                    ->options(MacroArea::class)
                    ->required(),
                TextInput::make('name')
                    ->required(),
                Select::make('type')
                    ->options(BusinessFunctionType::class)
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
                Select::make('outsourcable_status')
                    ->options(OutsourcableStatus::class)
                    ->default('no')
                    ->required(),
                TextInput::make('managed_by_id')
                    ->numeric(),
                Textarea::make('mission')
                    ->columnSpanFull(),
                Textarea::make('responsibility')
                    ->columnSpanFull(),
            ]);
    }
}
