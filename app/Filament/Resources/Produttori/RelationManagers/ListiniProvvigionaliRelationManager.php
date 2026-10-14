<?php

namespace App\Filament\Resources\Produttori\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ListiniProvvigionaliRelationManager extends RelationManager
{
    protected static string $relationship = 'listiniProvvigionali';

    protected static ?string $title = 'Listini';

    protected static ?string $modelLabel = 'Listino';

    protected static ?string $pluralModelLabel = 'Listini';

    /** Il resource Produttori è in sola lettura, ma i listini si gestiscono da qui. */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->allowDuplicates()
            ->columns([
                TextColumn::make('code')->label('Codice')->sortable(),
                TextColumn::make('name')->label('Listino')->searchable(),
                IconColumn::make('coordinamento')
                    ->label('Come coordinatore')
                    ->boolean(),
            ])
            ->defaultSort('code')
            ->headerActions([
                AttachAction::make()
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['code', 'name'])
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
