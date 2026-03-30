<?php

namespace App\Filament\Resources\Employees\RelationManagers;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Actions\AttachAction;
use Filament\Tables\Actions\DetachAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BusinessFunctionsRelationManager extends RelationManager
{
    protected static string $relationship = 'businessFunctions';

    protected static ?string $title = 'Funzioni Aziendali';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Toggle::make('is_manager')
                    ->label('Responsabile')
                    ->default(false),
                DatePicker::make('start_date')
                    ->label('Inizio Incarico'),
                DatePicker::make('end_date')
                    ->label('Fine Incarico'),
                TextInput::make('temporary_reason')
                    ->label('Causale (es. Interim)')
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('code')
                    ->label('Codice')
                    ->searchable(),
                TextColumn::make('name')
                    ->label('Funzione')
                    ->searchable(),
                IconColumn::make('is_manager')
                    ->label('Resp.')
                    ->boolean(),
                TextColumn::make('start_date')
                    ->label('Inizio')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('end_date')
                    ->label('Fine')
                    ->date('d/m/Y')
                    ->sortable()
                    ->placeholder('In corso'),
                TextColumn::make('temporary_reason')
                    ->label('Note')
                    ->limit(30),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                AttachAction::make()
                    ->form(fn (AttachAction $action): array => [
                        $action->getRecordSelect(),
                        Toggle::make('is_manager')->label('Responsabile'),
                        DatePicker::make('start_date')->label('Inizio Incarico'),
                        DatePicker::make('end_date')->label('Fine Incarico'),
                        TextInput::make('temporary_reason')->label('Causale'),
                    ]),
            ])
            ->actions([
                DetachAction::make(),
            ])
            ->bulkActions([
                //
            ]);
    }
}
