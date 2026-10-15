<?php

namespace App\Filament\Resources\ListinoProvvigionales\RelationManagers;

use App\Models\ListinoProvvigionale;
use App\Models\ListinoProvvigionaleVoce;
use App\Support\VociProvvigionali;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

/** Percentuali del listino per voce di compenso (es. standard e special, banca, mediazione e polizza). */
class VociRelationManager extends RelationManager
{
    protected static string $relationship = 'voci';

    protected static ?string $title = 'Voci di compenso';

    protected static ?string $modelLabel = 'Voce';

    protected static ?string $pluralModelLabel = 'Voci di compenso';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        /** @var ListinoProvvigionale $listino */
        $listino = $this->getOwnerRecord();

        return $schema->components([
            Select::make('voce')
                ->label('Voce')
                ->options(VociProvvigionali::per($listino->tipoprodotto?->name) ?: VociProvvigionali::tutte())
                ->required()
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('listino_provvigionale_id', $listino->getKey())),
            TextInput::make('percentuale')->label('Percentuale')->numeric()->minValue(0)->maxValue(100)->suffix('%')->required(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('voce')
            ->columns([
                TextColumn::make('voce')->label('Voce')->formatStateUsing(fn (string $state, ListinoProvvigionaleVoce $record): string => $record->etichetta()),
                TextColumn::make('percentuale')->label('Percentuale')->suffix('%'),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
