<?php

namespace App\Filament\Resources\BusinessFunctions\RelationManagers;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\AttachAction;
use Filament\Tables\Actions\DetachAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ConsultantsRelationManager extends RelationManager
{
    protected static string $relationship = 'clients';

    protected static ?string $title = 'Consulenti / Fornitori Esterni';

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
                TextColumn::make('name')
                    ->label('Ragione Sociale')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email')
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
                    ->recordSelectOptionsQuery(fn ($query) => $query->where('is_company', true))
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
