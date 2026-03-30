<?php

namespace App\Filament\Resources\Processes\RelationManagers;

use App\Services\BpmRegistryService;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
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
        $companyId = Filament::getCurrentPanel()->getId();

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
                Select::make('action_class')
                    ->label('Azione Automatica al Completamento')
                    ->searchable()
                    ->clearable()
                    // Richiamiamo la nostra magia!
                    ->options(fn () => BpmRegistryService::getOptionsForFilament('actions', $companyId)),
                Select::make('skip_condition_class')
                    ->label('Escludi la voce se...')
                    ->searchable()
                    ->clearable()
                    ->options(fn () => BpmRegistryService::getOptionsForFilament('conditions', $companyId)),
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
                CheckboxList::make('privacyDataTypes')
                    ->relationship(
                        name: 'privacyDataTypes',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn ($query) => $query->orderBy('category')->orderBy('name')
                    )
                    ->descriptions([
                        'HEALTH_DATA' => 'Richiede misure di sicurezza elevate (Crittografia).',
                        'FIN_CREDIT' => 'Dati critici per il merito creditizio.',
                        'CRIMINAL_REC' => 'Dati relativi a condanne penali o reati.',
                    ])
                    ->columns(2)
                    ->bulkToggleable(),  // Comodo per l'utente
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
