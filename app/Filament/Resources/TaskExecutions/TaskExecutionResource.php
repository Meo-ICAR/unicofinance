<?php

namespace App\Filament\Resources\TaskExecutions;

use App\Filament\Resources\TaskExecutions\Pages\CreateTaskExecution;
use App\Filament\Resources\TaskExecutions\Pages\EditTaskExecution;
use App\Filament\Resources\TaskExecutions\Pages\ListTaskExecutions;
use App\Filament\Resources\TaskExecutions\Schemas\TaskExecutionForm;
use App\Filament\Resources\TaskExecutions\Tables\TaskExecutionsTable;
use App\Models\TaskExecution;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TaskExecutionResource extends Resource
{
    protected static ?string $model = TaskExecution::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return TaskExecutionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TaskExecutionsTable::configure($table);
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
            'index' => ListTaskExecutions::route('/'),
            'create' => CreateTaskExecution::route('/create'),
            'edit' => EditTaskExecution::route('/{record}/edit'),
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
