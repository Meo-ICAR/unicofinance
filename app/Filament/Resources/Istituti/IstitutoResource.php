<?php

namespace App\Filament\Resources\Istituti;

use App\Filament\Resources\Istituti\Pages\ListIstituti;
use App\Filament\Resources\Istituti\Pages\ViewIstituto;
use App\Filament\Resources\Istituti\RelationManagers\ProduttoriBloccatiRelationManager;
use App\Filament\Resources\Istituti\Schemas\IstitutoInfolist;
use App\Filament\Resources\Istituti\Tables\IstitutiTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Unico\Core\Models\Cliente;

/** Banche e istituti finanziari (clienti del mediatore), in sola lettura. */
class IstitutoResource extends Resource
{
    protected static ?string $model = Cliente::class;

    protected static ?string $slug = 'istituti';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static ?string $recordTitleAttribute = 'name';

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

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return IstitutoInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IstitutiTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ProduttoriBloccatiRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIstituti::route('/'),
            'view' => ViewIstituto::route('/{record}'),
        ];
    }
}
