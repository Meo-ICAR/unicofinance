<?php

namespace App\Filament\Resources\Companies\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CompanyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('vat_number'),
                TextInput::make('vat_name'),
                TextInput::make('oam'),
                DatePicker::make('oam_at'),
                TextInput::make('oam_name'),
                TextInput::make('numero_iscrizione_rui'),
                TextInput::make('ivass'),
                DatePicker::make('ivass_at'),
                TextInput::make('ivass_name'),
                Select::make('ivass_section')
                    ->options(['A' => 'A', 'B' => 'B', 'C' => 'C', 'D' => 'D', 'E' => 'E']),
                TextInput::make('sponsor'),
                Select::make('company_type')
                    ->options([
            'mediatore' => 'Mediatore',
            'call center' => 'Call center',
            'hotel' => 'Hotel',
            'sw house' => 'Sw house',
        ]),
                Textarea::make('page_header')
                    ->columnSpanFull(),
                Textarea::make('page_footer')
                    ->columnSpanFull(),
                TextInput::make('smtp_host'),
                TextInput::make('smtp_port')
                    ->numeric(),
                TextInput::make('smtp_username'),
                TextInput::make('smtp_password')
                    ->password(),
                TextInput::make('smtp_encryption'),
                TextInput::make('smtp_from_email')
                    ->email(),
                TextInput::make('smtp_from_name'),
                Toggle::make('smtp_enabled')
                    ->required(),
                Toggle::make('smtp_verify_ssl')
                    ->required(),
            ]);
    }
}
