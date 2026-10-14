<?php

namespace App\Filament\Resources\ListinoProvvigionales\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FornitoriRelationManager extends RelationManager
{
    protected static string $relationship = 'fornitori';

    protected static ?string $title = 'Produttori';

    protected static ?string $modelLabel = 'Produttore';

    protected static ?string $pluralModelLabel = 'Produttori';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->allowDuplicates()
            ->columns([
                TextColumn::make('name')->label('Produttore')->searchable(),
                IconColumn::make('coordinamento')
                    ->label('Come coordinatore')
                    ->boolean(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->preloadRecordSelect()
                    ->schema(fn (AttachAction $action): array => [
                        $action->getRecordSelect(),
                        Toggle::make('coordinamento')->label('Listino come coordinatore'),
                    ]),
            ])
            ->recordActions([
                DetachAction::make(),
            ]);
    }
}
