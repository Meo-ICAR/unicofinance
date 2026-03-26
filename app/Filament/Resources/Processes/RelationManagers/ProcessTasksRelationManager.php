<?php

namespace App\Filament\Resources\Processes\RelationManagers;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProcessTasksRelationManager extends RelationManager
{
    protected static string $relationship = 'tasks';

    protected static ?string $title = 'Fasi / Task del Processo';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('sequence_number')
                    ->label('N° Sequenza')
                    ->numeric()
                    ->default(0)
                    ->required(),
                TextInput::make('name')
                    ->label('Nome Fase')
                    ->required()
                    ->maxLength(255),
                Select::make('business_function_id')
                    ->label('Funzione Competente')
                    ->relationship('businessFunction', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Textarea::make('description')
                    ->label('Descrizione Operativa')
                    ->columnSpanFull(),
                
                Repeater::make('raciAssignments')
                    ->relationship()
                    ->label('Matrice RACI (Responsabilità)')
                    ->schema([
                        Select::make('business_function_id')
                            ->label('Funzione')
                            ->relationship('businessFunction', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('role')
                            ->label('Ruolo')
                            ->options([
                                'R' => 'R - Responsible (Esegue)',
                                'A' => 'A - Accountable (Approva)',
                                'C' => 'C - Consulted (Consultato)',
                                'I' => 'I - Informed (Informato)',
                            ])
                            ->required(),
                    ])
                    ->columns(2)
                    ->columnSpanFull()
                    ->defaultItems(0),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('sequence_number')
            ->reorderable('sequence_number')
            ->columns([
                TextColumn::make('sequence_number')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Fase')
                    ->searchable(),
                TextColumn::make('businessFunction.name')
                    ->label('Funzione Resp.')
                    ->badge()
                    ->color('warning'),
                TextColumn::make('description')
                    ->label('Descrizione')
                    ->limit(50),
                TextColumn::make('raciAssignments.role')
                    ->label('RACI')
                    ->badge()
                    ->separator(',')
                    ->color('info'),
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
