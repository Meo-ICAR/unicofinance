<?php

namespace App\Filament\Resources\Istituti;

use App\Filament\Resources\Istituti\Pages\EditIstituto;
use App\Filament\Resources\Istituti\Pages\ListIstituti;
use App\Filament\Resources\Istituti\RelationManagers\DocumentiIstitutoRelationManager;
use App\Filament\Resources\Istituti\RelationManagers\PortaliIstitutoRelationManager;
use App\Filament\Resources\Istituti\RelationManagers\ProduttoriBloccatiRelationManager;
use App\Filament\Resources\Istituti\RelationManagers\ProvvigioniAttiveRelationManager;
use App\Filament\Resources\Istituti\Tables\IstitutiTable;
use App\Models\User;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Unico\Core\Filament\RelationManagers\DocumentsRelationManager;
use Unico\Core\Models\Cliente;

/** Banche e istituti finanziari (clienti del mediatore), in sola lettura. */
class IstitutoResource extends Resource
{
    protected static ?string $model = Cliente::class;

    protected static ?string $slug = 'istituti';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\UnitEnum|null $navigationGroup = 'Configurazione';

    protected static ?string $navigationLabel = 'Istituti';

    protected static ?string $modelLabel = 'Istituto';

    protected static ?string $pluralModelLabel = 'Istituti';

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isStaff();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    /** Dell'istituto si modificano solo i riferimenti web; il resto è anagrafica condivisa. */
    public static function canEdit(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Istituto')->disabled()->dehydrated(false),
            TextInput::make('abi')->label('ABI')->disabled()->dehydrated(false),
            TextInput::make('piva')->label('P. IVA')->disabled()->dehydrated(false),
            TextInput::make('email')->label('Email')->disabled()->dehydrated(false),
            Toggle::make('is_active')->label('Attivo')->disabled()->dehydrated(false),
            TextInput::make('website')->label('Sito')->url()->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return IstitutiTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            DocumentsRelationManager::class,
            DocumentiIstitutoRelationManager::class,
            PortaliIstitutoRelationManager::class,
            ProvvigioniAttiveRelationManager::class,
            ProduttoriBloccatiRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIstituti::route('/'),
            'edit' => EditIstituto::route('/{record}/edit'),
        ];
    }
}
