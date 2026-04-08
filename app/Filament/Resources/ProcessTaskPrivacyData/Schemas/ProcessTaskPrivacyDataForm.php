<?php

namespace App\Filament\Resources\ProcessTaskPrivacyData\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProcessTaskPrivacyDataForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('process_task_id')
                    ->required()
                    ->numeric(),
                Select::make('privacy_data_type_id')
                    ->relationship('privacyDataType', 'name')
                    ->required(),
                Select::make('access_level')
                    ->options(['read' => 'Read', 'write' => 'Write', 'update' => 'Update', 'delete' => 'Delete'])
                    ->default('read')
                    ->required(),
                TextInput::make('purpose'),
                TextInput::make('privacy_legal_base_id')
                    ->numeric(),
                TextInput::make('retention_period'),
                Toggle::make('is_encrypted')
                    ->required(),
                Toggle::make('is_shared_externally')
                    ->required(),
                TextInput::make('created_by')
                    ->numeric(),
                TextInput::make('updated_by')
                    ->numeric(),
            ]);
    }
}
