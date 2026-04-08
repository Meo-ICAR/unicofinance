<?php

namespace App\Filament\Resources\RaciAssignments;

use App\Filament\Resources\RaciAssignments\Pages\CreateRaciAssignment;
use App\Filament\Resources\RaciAssignments\Pages\EditRaciAssignment;
use App\Filament\Resources\RaciAssignments\Pages\ListRaciAssignments;
use App\Filament\Resources\RaciAssignments\Schemas\RaciAssignmentForm;
use App\Filament\Resources\RaciAssignments\Tables\RaciAssignmentsTable;
use App\Models\RaciAssignment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RaciAssignmentResource extends Resource
{
    protected static ?string $model = RaciAssignment::class;
    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return RaciAssignmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RaciAssignmentsTable::configure($table);
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
            'index' => ListRaciAssignments::route('/'),
            'create' => CreateRaciAssignment::route('/create'),
            'edit' => EditRaciAssignment::route('/{record}/edit'),
        ];
    }
}
