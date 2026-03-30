<?php

namespace App\Filament\Resources\Clients\RelationManagers;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Actions\AttachAction;
use Filament\Tables\Actions\DetachAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BusinessFunctionsRelationManager extends RelationManager
{
    protected static string $relationship = 'businessFunctions';

    protected static ?string $title = 'Funzioni Aziendali Presidiate';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                DatePicker::make('start_date')
                    ->label('Inizio Incarico'),
                DatePicker::make('end_date')
                    ->label('Fine Incarico (Scadenza)'),
                TextInput::make('temporary_reason')
                    ->label('Note / Causale')
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
                TextColumn::make('start_date')
                    ->label('Inizio')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('end_date')
                    ->label('Fine/Scadenza')
                    ->date('d/m/Y')
                    ->sortable()
                    ->placeholder('In corso'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                AttachAction::make()
                    ->form(fn (AttachAction $action): array => [
                        $action->getRecordSelect(),
                        DatePicker::make('start_date')->label('Inizio Incarico'),
                        DatePicker::make('end_date')->label('Fine/Scadenza'),
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
