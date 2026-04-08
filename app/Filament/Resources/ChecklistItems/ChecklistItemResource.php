<?php

namespace App\Filament\Resources\ChecklistItems;

use App\Filament\Resources\ChecklistItems\Pages\CreateChecklistItem;
use App\Filament\Resources\ChecklistItems\Pages\EditChecklistItem;
use App\Filament\Resources\ChecklistItems\Pages\ListChecklistItems;
use App\Filament\Resources\ChecklistItems\Schemas\ChecklistItemForm;
use App\Filament\Resources\ChecklistItems\Tables\ChecklistItemsTable;
use App\Models\ChecklistItem;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ChecklistItemResource extends Resource
{
    protected static ?string $model = ChecklistItem::class;
    protected static bool $shouldRegisterNavigation = false;
    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ChecklistItemForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ChecklistItemsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListChecklistItems::route('/'),
            'create' => CreateChecklistItem::route('/create'),
            'edit' => EditChecklistItem::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
