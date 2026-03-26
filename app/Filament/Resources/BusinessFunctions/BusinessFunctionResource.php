<?php

namespace App\Filament\Resources\BusinessFunctions;

use App\Filament\Resources\BusinessFunctions\Pages\CreateBusinessFunction;
use App\Filament\Resources\BusinessFunctions\Pages\EditBusinessFunction;
use App\Filament\Resources\BusinessFunctions\Pages\ListBusinessFunctions;
use App\Filament\Resources\BusinessFunctions\RelationManagers\EmployeesRelationManager;
use App\Filament\Resources\BusinessFunctions\RelationManagers\ConsultantsRelationManager;
use App\Filament\Resources\BusinessFunctions\RelationManagers\ProcessesRelationManager;
use App\Filament\Resources\BusinessFunctions\Schemas\BusinessFunctionForm;
use App\Filament\Resources\BusinessFunctions\Tables\BusinessFunctionsTable;
use App\Models\BusinessFunction;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class BusinessFunctionResource extends Resource
{
    protected static ?string $model = BusinessFunction::class;

    protected static ?string $tenantOwnershipRelationshipName = 'company';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-group';

    protected static string|UnitEnum|null $navigationGroup = 'Organigramma';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return BusinessFunctionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BusinessFunctionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            EmployeesRelationManager::class,
            ConsultantsRelationManager::class,
            ProcessesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBusinessFunctions::route('/'),
            'create' => CreateBusinessFunction::route('/create'),
            'edit' => EditBusinessFunction::route('/{record}/edit'),
        ];
    }
}
