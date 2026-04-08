<?php

namespace App\Filament\Resources\BusinessFunctions\RelationManagers;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ConsultantsRelationManager extends RelationManager
{
    protected static string $relationship = 'clients';

    protected static ?string $title = 'Consulenti Esterni';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                DatePicker::make('start_date')
                ->default(now())
                    ->label('Inizio Incarico'),
                        TextInput::make('temporary_reason')
                    ->label('Annotazioni ( es. interim)')
                    ->maxLength(255),
                DatePicker::make('end_date')
                    ->label('Fine Incarico (Scadenza)'),

            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Denominazione')
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
                        TextInput::make('temporary_reason')->label('Causale es. Interim'),
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
