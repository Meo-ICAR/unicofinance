<?php

namespace App\Filament\RelationManagers;

use App\Models\Client;
use App\Models\Pratica;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/** Indirizzi del richiedente (residenza, domicilio). Se non c'è anagrafica, restano sulla pratica. */
class AddressesRelationManager extends RelationManager
{
    protected static string $relationship = 'addresses';

    protected static ?string $title = 'Indirizzi';

    protected static ?string $modelLabel = 'Indirizzo';

    protected static ?string $pluralModelLabel = 'Indirizzi';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        // Le persone giuridiche non hanno residenza e domicilio: le loro sedi sono nelle sedi (branches).
        return $ownerRecord instanceof Pratica || ($ownerRecord instanceof Client && (bool) $ownerRecord->is_person);
    }

    public function getRelationship(): Relation|Builder
    {
        $owner = $this->getOwnerRecord();

        if ($owner instanceof Pratica) {
            $client = $owner->richiedente;

            if ($client instanceof Client) {
                return $client->addresses();
            }
        }

        return parent::getRelationship();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('address_type_id')
                ->label('Tipo')
                ->relationship('addressType', 'name')
                ->preload(),
            TextInput::make('name')->label('Descrizione')->maxLength(255),
            TextInput::make('street')->label('Via / Piazza')->required()->maxLength(255),
            TextInput::make('numero')->label('Civico')->maxLength(255),
            TextInput::make('zip_code')->label('CAP')->maxLength(20),
            TextInput::make('city')->label('Comune')->maxLength(255),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('street')
            ->columns([
                TextColumn::make('addressType.name')->label('Tipo')->badge()->placeholder('—'),
                TextColumn::make('street')
                    ->label('Indirizzo')
                    ->formatStateUsing(fn (string $state, Model $record): string => trim($state.' '.($record->numero ?? ''))),
                TextColumn::make('zip_code')->label('CAP')->placeholder('—'),
                TextColumn::make('city')->label('Comune')->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
