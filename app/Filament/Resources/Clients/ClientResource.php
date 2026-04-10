<?php

namespace App\Filament\Resources\Clients;

use App\Filament\Resources\Clients\Pages\CreateClient;
use App\Filament\Resources\Clients\Pages\EditClient;
use App\Filament\Resources\Clients\Pages\ListClients;
use App\Filament\Resources\Clients\RelationManagers\BusinessFunctionsRelationManager;
use App\Filament\Resources\Clients\Schemas\ClientForm;
use App\Filament\Resources\Clients\Tables\ClientsTable;
use App\Models\Client;
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

class ClientResource extends Resource
{
    protected static ?string $model = Client::class;

    protected static string|UnitEnum|null $navigationGroup = 'Organigramma';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $tenantOwnershipRelationshipName = 'company';

    public static function form(Schema $schema): Schema
    {
        return ClientForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClientsTable::configure($table);
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
            'index' => ListClients::route('/'),
            'create' => CreateClient::route('/create'),
            'edit' => EditClient::route('/{record}/edit'),
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
     * Filter clients to only those the current user can see
     * based on TaskExecution assignments.
     *
     * A user can see:
     *  - Clients where they are the assignee on a TaskExecution
     *  - Clients that are the target of a TaskExecution assigned to them
     *  - If supervisor: also clients assigned to their subordinates
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

        // No employee record = no access to clients
        if (! $employee) {
            return $query->whereRaw('1 = 0');
        }

        $targetType = Client::class;

        return $query->where(function (Builder $q) use ($employee, $targetType) {
            // Direct assignment: user is the assignee on a TaskExecution targeting this client
            // target_type is set from Process.target_model by StartProcessAction / EmployeeObserver
            $q->whereHas('taskExecutions', function (Builder $sub) use ($employee, $targetType) {
                $sub->where('employee_id', $employee->id)
                    ->where('target_type', $targetType);
            });

            // Target assignment: client is the target of a TaskExecution assigned to this user
            $q->orWhereHas('taskExecutionsAsTarget', function (Builder $sub) use ($employee) {
                $sub->where('employee_id', $employee->id)
                    ->orWhereNull('employee_id');
            });

            // Supervisor: also see clients assigned to subordinates
            if ($employee->supervisor_type !== 'no') {
                $subordinateIds = Employee::where('coordinated_by_id', $employee->id)->pluck('id');

                if ($subordinateIds->isNotEmpty()) {
                    $q->orWhereHas('taskExecutions', function (Builder $sub) use ($subordinateIds, $targetType) {
                        $sub->whereIn('employee_id', $subordinateIds)
                            ->where('target_type', $targetType);
                    });
                }
            }
        });
    }
}
