<?php

namespace App\Filament\Resources\Employees;

use App\Filament\Resources\Employees\Pages\CreateEmployee;
use App\Filament\Resources\Employees\Pages\EditEmployee;
use App\Filament\Resources\Employees\Pages\ListEmployees;
use App\Filament\Resources\Employees\RelationManagers\BusinessFunctionsRelationManager;
use App\Filament\Resources\Employees\Schemas\EmployeeForm;
use App\Filament\Resources\Employees\Tables\EmployeesTable;
use App\Models\Employee;
use App\Models\TaskExecution;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static string|UnitEnum|null $navigationGroup = 'Organigramma';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Dipendenti';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $tenantOwnershipRelationshipName = 'company';

    public static function form(Schema $schema): Schema
    {
        return EmployeeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmployeesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            BusinessFunctionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmployees::route('/'),
            'create' => CreateEmployee::route('/create'),
            'edit' => EditEmployee::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return static::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    /**
     * Filter employees to only those the current user can see
     * based on TaskExecution assignments.
     *
     * A user can see:
     *  - Themselves (their own employee record)
     *  - Employees where they are the assignee on a TaskExecution
     *  - Employees that are the target of a TaskExecution assigned to them
     *  - If supervisor: also employees assigned to their subordinates
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        // Super admin sees everything
        if ($user->is_super_admin) {
            return $query;
        }

        $employee = $user->employee;

        // No employee record = no access
        if (! $employee) {
            return $query->whereRaw('1 = 0');
        }

        $targetType = Employee::class;

        return $query->where(function (Builder $q) use ($employee, $targetType) {
            // Always see your own record
            $q->where('employees.id', $employee->id);

            // Employees where this user is the assignee on a TaskExecution targeting Employee
            $q->orWhereHas('taskExecutions', function (Builder $sub) use ($employee, $targetType) {
                $sub->where('employee_id', $employee->id)
                    ->where('target_type', $targetType);
            });

            // Employees where this user is the target of a process
            $q->orWhereHas('taskExecutionsAsTarget', function (Builder $sub) use ($employee) {
                $sub->where('employee_id', $employee->id)
                    ->orWhereNull('employee_id');
            });

            // Supervisor: also see subordinates
            if ($employee->supervisor_type !== 'no') {
                $subordinateIds = Employee::where('coordinated_by_id', $employee->id)->pluck('id');

                if ($subordinateIds->isNotEmpty()) {
                    $q->orWhere(function (Builder $subQ) use ($subordinateIds, $targetType) {
                        $subQ->whereIn('employees.id', $subordinateIds);
                        $subQ->orWhereHas('taskExecutions', function (Builder $sub) use ($subordinateIds, $targetType) {
                            $sub->whereIn('employee_id', $subordinateIds)
                                ->where('target_type', $targetType);
                        });
                    });
                }
            }
        });
    }
}
