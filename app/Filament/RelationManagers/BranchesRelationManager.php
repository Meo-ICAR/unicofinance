<?php

namespace App\Filament\RelationManagers;

use App\Models\Pratica;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Unico\Core\Models\Fornitore;

class BranchesRelationManager extends RelationManager
{
    protected static string $relationship = 'branches';

    protected static ?string $title = 'Indirizzi';

    protected static ?string $modelLabel = 'Indirizzo';

    protected static ?string $pluralModelLabel = 'Indirizzi';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Pratica || $ownerRecord instanceof Fornitore;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Sede')
                ->required()
                ->maxLength(255),
            Toggle::make('is_active')->label('Attiva')->default(true),
            Toggle::make('is_main_office')->label('Sede principale')->default(false),
            DatePicker::make('founded_at')->label('Data apertura')->native(false)->displayFormat('d/m/Y'),
            DatePicker::make('dismissed_at')->label('Data chiusura')->native(false)->displayFormat('d/m/Y'),
            TextInput::make('address')->label('Via / Piazza'),
            TextInput::make('street_number')->label('Numero civico')->maxLength(20),
            TextInput::make('city')->label('Città'),
            TextInput::make('zip_code')->label('CAP')->maxLength(10),
            TextInput::make('province')->label('Provincia'),
            TextInput::make('region')->label('Regione'),
            TextInput::make('manager_first_name')->label('Nome responsabile'),
            TextInput::make('manager_last_name')->label('Cognome responsabile'),
            TextInput::make('manager_tax_code')->label('Codice fiscale responsabile')->maxLength(16),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->label('Sede')->searchable(),
                IconColumn::make('is_active')->label('Attiva')->boolean(),
                IconColumn::make('is_main_office')->label('Principale')->boolean(),
                TextColumn::make('city')->label('Città')->searchable(),
                TextColumn::make('province')->label('Provincia'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['company_id'] = $this->getOwnerRecord()->company_id;

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
