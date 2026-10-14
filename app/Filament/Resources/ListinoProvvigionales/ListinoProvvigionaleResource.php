<?php

namespace App\Filament\Resources\ListinoProvvigionales;

use App\Filament\Resources\ListinoProvvigionales\Pages\CreateListinoProvvigionale;
use App\Filament\Resources\ListinoProvvigionales\Pages\EditListinoProvvigionale;
use App\Filament\Resources\ListinoProvvigionales\Pages\ListListinoProvvigionales;
use App\Filament\Resources\ListinoProvvigionales\Pages\ViewListinoProvvigionale;
use App\Filament\Resources\ListinoProvvigionales\RelationManagers\FornitoriRelationManager;
use App\Filament\Resources\ListinoProvvigionales\Schemas\ListinoProvvigionaleForm;
use App\Filament\Resources\ListinoProvvigionales\Schemas\ListinoProvvigionaleInfolist;
use App\Filament\Resources\ListinoProvvigionales\Tables\ListinoProvvigionalesTable;
use App\Models\ListinoProvvigionale;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ListinoProvvigionaleResource extends Resource
{
    protected static ?string $model = ListinoProvvigionale::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $slug = 'listini-provvigionali';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Listini provvigionali';

    protected static ?string $modelLabel = 'Listino provvigionale';

    protected static ?string $pluralModelLabel = 'Listini provvigionali';

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isStaff();
    }

    public static function form(Schema $schema): Schema
    {
        return ListinoProvvigionaleForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ListinoProvvigionaleInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ListinoProvvigionalesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            FornitoriRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListListinoProvvigionales::route('/'),
            'create' => CreateListinoProvvigionale::route('/create'),
            'view' => ViewListinoProvvigionale::route('/{record}'),
            'edit' => EditListinoProvvigionale::route('/{record}/edit'),
        ];
    }
}
