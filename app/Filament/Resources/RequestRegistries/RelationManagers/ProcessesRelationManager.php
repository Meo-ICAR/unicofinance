<?php

namespace App\Filament\Resources\RequestRegistries\RelationManagers;

use App\Models\Process;
use App\Models\ProcessTask;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Get;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProcessesRelationManager extends RelationManager
{
    protected static string $relationship = 'processes';

    protected static ?string $label = 'Processi Eseguiti';

    protected static ?string $inverseLabel = 'Richiesta';

    public function form(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return $schema
            ->components([
                Select::make('process_id')
                    ->label('Processo')
                    ->options(Process::query()->pluck('name', 'id'))
                    ->searchable()
                    ->live()
                    ->required(),
                Select::make('process_task_id')
                    ->label('Task (opzionale)')
                    ->options(fn (Get $get) => ProcessTask::query()
                        ->where('process_id', $get('process_id'))
                        ->orderBy('sequence_number')
                        ->pluck('name', 'id'))
                    ->searchable()
                    ->visible(fn (Get $get) => filled($get('process_id'))),
                TextInput::make('outcome')
                    ->label('Esito')
                    ->columnSpanFull(),
                DateTimePicker::make('completed_at')
                    ->label('Completato il')
                    ->native(false),
            ])
            ->columns(1);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('process.name')
                    ->label('Processo')
                    ->searchable(),
                TextColumn::make('processTask.name')
                    ->label('Task')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('outcome')
                    ->label('Esito')
                    ->limit(60),
                TextColumn::make('completed_at')
                    ->label('Completato il')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}
