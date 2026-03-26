<?php

namespace App\Filament\Resources\Users\RelationManagers;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SocialiteUsersRelationManager extends RelationManager
{
    protected static string $relationship = 'socialiteUsers';

    protected static ?string $recordTitleAttribute = 'email';

    protected static ?string $title = 'Account Associati (Socialite)';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('provider')
                    ->label('Provider')
                    ->default('microsoft')
                    ->required()
                    ->disabled(fn (string $operation): bool => $operation === 'edit'),
                TextInput::make('provider_id')
                    ->label('Provider ID')
                    ->default(function () { return \Illuminate\Support\Str::random(10); })
                    ->required()
                    ->disabled(fn (string $operation): bool => $operation === 'edit'),
                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->disabled(fn (string $operation): bool => $operation === 'edit'),
                Toggle::make('is_personal')
                    ->label('Account Personale?')
                    ->default(false),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('avatar')
                    ->label('Avatar')
                    ->circular(),
                TextColumn::make('provider')
                    ->label('Provider')
                    ->badge(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                IconColumn::make('is_personal')
                    ->label('Personale')
                    ->boolean(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // Di solito gli account socialite vengono collegati dall'utente,
                // ma lasciamo la creatio disabilitata da admin a meno che non sia richiesta.
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        // Impedisce di cambiare email/provider
                        unset($data['provider'], $data['email']);
                        return $data;
                    }),
                DeleteAction::make(),
            ])
            ->bulkActions([
                //
            ]);
    }
}
