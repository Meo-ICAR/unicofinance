<?php

namespace App\Filament\Resources\EmployeeTypes\RelationManagers;

use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Unico\Core\Models\Resource;

/** Matrice dei permessi del ruolo: una riga per risorsa dell'applicazione, un interruttore per azione. */
class PermissionsRelationManager extends RelationManager
{
    protected static string $relationship = 'employeeTypePermissions';

    protected static ?string $title = 'Matrice permessi e accessi';

    /** @var list<string> */
    private const ACTIONS = ['viewAny', 'view', 'create', 'update', 'delete'];

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Resource::query()->forCurrentApp())
            ->columns([
                TextColumn::make('name')
                    ->label('Risorsa')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Resource $record): string => "Chiave: {$record->key}"),
                TextColumn::make('group')->label('Gruppo')->badge()->color('gray')->sortable(),
                ToggleColumn::make('all_permissions')
                    ->label('Tutti')
                    ->getStateUsing(fn (Resource $record): bool => $this->permissionsOf($record)->count() === count(self::ACTIONS))
                    ->updateStateUsing(function (Resource $record, bool $state): void {
                        foreach (self::ACTIONS as $action) {
                            $this->togglePermission($record, $action, $state);
                        }
                    }),
                $this->actionColumn('viewAny', 'Elenco'),
                $this->actionColumn('view', 'Dettaglio'),
                $this->actionColumn('create', 'Crea'),
                $this->actionColumn('update', 'Modifica'),
                $this->actionColumn('delete', 'Elimina'),
            ])
            ->filters([
                SelectFilter::make('group')
                    ->label('Gruppo')
                    ->options(fn (): array => Resource::query()->forCurrentApp()->whereNotNull('group')->pluck('group', 'group')->all()),
            ])
            ->headerActions([
                Action::make('grantAll')
                    ->label('Abilita tutto')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (): void {
                        foreach (Resource::query()->forCurrentApp()->get() as $resource) {
                            foreach (self::ACTIONS as $action) {
                                $this->togglePermission($resource, $action, true);
                            }
                        }
                    }),
                Action::make('revokeAll')
                    ->label('Revoca tutto')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn () => $this->getOwnerRecord()->employeeTypePermissions()
                        ->whereIn('resource_id', Resource::query()->forCurrentApp()->select('id'))
                        ->delete()),
            ]);
    }

    private function actionColumn(string $action, string $label): ToggleColumn
    {
        return ToggleColumn::make('perm_'.$action)
            ->label($label)
            ->getStateUsing(fn (Resource $record): bool => $this->permissionsOf($record)->where('action', $action)->exists())
            ->updateStateUsing(fn (Resource $record, bool $state) => $this->togglePermission($record, $action, $state));
    }

    private function permissionsOf(Resource $resource)
    {
        return $this->getOwnerRecord()->employeeTypePermissions()
            ->where('resource_id', $resource->getKey())
            ->whereIn('action', self::ACTIONS);
    }

    private function togglePermission(Resource $resource, string $action, bool $state): void
    {
        $permissions = $this->getOwnerRecord()->employeeTypePermissions();

        if ($state) {
            $permissions->firstOrCreate(['resource_id' => $resource->getKey(), 'action' => $action]);

            return;
        }

        $permissions->where('resource_id', $resource->getKey())->where('action', $action)->delete();
    }
}
