<?php

namespace App\Filament\Resources\RequestRegistries\RelationManagers;

use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ActionsRelationManager extends RelationManager
{
    protected static string $relationship = 'actions';

    protected static ?string $label = 'Log Azioni';

    protected static ?string $inverseLabel = 'Richiesta';

    public function form(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return $schema
            ->components([
                DateTimePicker::make('action_date')
                    ->label('Data/Ora')
                    ->required()
                    ->default(now())
                    ->native(false),
                Select::make('action_type')
                    ->label('Tipo Azione')
                    ->options([
                        'assegnazione' => 'Assegnazione',
                        'inoltro' => 'Inoltro',
                        'risposta_preliminare' => 'Risposta Preliminare',
                        'evasione' => 'Evasione',
                        'estensione_termini' => 'Estensione Termini',
                        'reclamo_interno' => 'Reclamo Interno',
                    ])
                    ->required(),
                Textarea::make('description')
                    ->label('Descrizione')
                    ->rows(3)
                    ->required(),
                Select::make('performed_by')
                    ->label('Eseguita da')
                    ->options(User::query()->pluck('name', 'id'))
                    ->searchable()
                    ->default(auth()->id()),
            ])
            ->columns(1);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('action_date')
                    ->label('Data/Ora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                BadgeColumn::make('action_type')
                    ->label('Tipo')
                    ->formatStateUsing(fn (string $state) => ucfirst(str_replace('_', ' ', $state)))
                    ->colors([
                        'info' => 'assegnazione',
                        'warning' => 'inoltro',
                        'info' => 'risposta_preliminare',
                        'success' => 'evasione',
                        'danger' => 'estensione_termini',
                        'gray' => 'reclamo_interno',
                    ]),
                TextColumn::make('description')
                    ->label('Descrizione')
                    ->limit(80),
                TextColumn::make('performedBy.name')
                    ->label('Eseguita da')
                    ->placeholder('—'),
            ])
            ->defaultSort('action_date', 'desc')
            ->headerActions([
                CreateAction::make(),
            ])
            ->paginated([10, 25]);
    }
}
