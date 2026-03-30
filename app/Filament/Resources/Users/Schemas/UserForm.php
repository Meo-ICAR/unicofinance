<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                DateTimePicker::make('email_verified_at'),
                TextInput::make('password')
                    ->password()
                    ->required(),
                // Campo Ruolo (Virtuale, non esiste su 'users' ma lo salveremo nella pivot)
                Select::make('tenant_role')
                    ->label('Ruolo Aziendale')
                    ->options([
                        'admin' => 'Admin',
                        'inspector' => 'Inspector',
                        'user' => 'User',
                    ])
                    ->default('user')
                    ->required()
                    ->dehydrated(false),  // Diciamo a Filament di NON provare a salvarlo nella tabella users
                // Campo visibile SOLO ai Super Admin per assegnare più aziende
                Select::make('companies')
                    ->relationship('companies', 'name')
                    ->multiple()
                    ->preload()
                    ->visible(fn () => auth()->user()->is_super_admin),
                Toggle::make('is_approved')
                    ->label('Approvato')
                    ->helperText('Abilità l\'accesso al pannello per questo utente.')
                    ->default(false),
                Toggle::make('is_super_admin')
                    ->label('Super Admin')
                    ->visible(fn () => auth()->user()->is_super_admin),
            ]);
    }
}
