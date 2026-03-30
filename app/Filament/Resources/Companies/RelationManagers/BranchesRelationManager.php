<?php

namespace App\Filament\Resources\Companies\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BranchesRelationManager extends RelationManager
{
    protected static string $relationship = 'branches';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('name')
                    ->label('Nome Sede')
                    ->required()
                    ->maxLength(255),
                Toggle::make('is_main_office')
                    ->label('Sede Legale / Principale')
                    ->default(false),
                TextInput::make('manager_first_name')
                    ->label('Nome Responsabile')
                    ->maxLength(100),
                TextInput::make('manager_last_name')
                    ->label('Cognome Responsabile')
                    ->maxLength(100),
                TextInput::make('manager_tax_code')
                    ->label('Codice Fiscale')
                    ->maxLength(16),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Sede')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_main_office')
                    ->label('Sede Legale')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('manager_first_name')
                    ->label('Nome Resp.')
                    ->searchable(),
                TextColumn::make('manager_last_name')
                    ->label('Cognome Resp.')
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                //
            ]);
    }
}
