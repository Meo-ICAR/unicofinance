<?php

namespace App\Filament\RelationManagers;

use App\Models\Pratica;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ProvvigioniRelationManager extends RelationManager
{
    protected static string $relationship = 'provvigioni';

    protected static ?string $title = 'Provvigioni';

    protected static ?string $modelLabel = 'Provvigione';

    protected static ?string $pluralModelLabel = 'Provvigioni';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Pratica && ! $ownerRecord->is_notowned;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('denominazione_riferimento')->label('Produttore'),
            TextInput::make('importo')->label('Importo')->numeric()->prefix('€'),
            TextInput::make('descrizione')->label('Descrizione')->required()->maxLength(255),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('entrata_uscita')
                    ->label('Entrata/Uscita')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'Entrata' => 'success',
                        'Uscita' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('denominazione_riferimento')->label('Produttore'),
                TextColumn::make('importo')->label('Importo')->money('EUR'),
                TextColumn::make('descrizione')->label('Descrizione'),
                TextColumn::make('status_compenso')->label('Stato compenso'),
                TextColumn::make('data_status')->label('Data stato')->date('d/m/Y'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
