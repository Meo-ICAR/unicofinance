<?php

namespace App\Filament\Resources\EmployeeTypes;

use App\Filament\Resources\EmployeeTypes\Pages\ListEmployeeTypes;
use App\Filament\Resources\EmployeeTypes\Pages\ViewEmployeeType;
use App\Filament\Resources\EmployeeTypes\RelationManagers\PermissionsRelationManager;
use App\Filament\Traits\HasPlanAccess;
use App\Models\User;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Unico\Core\Models\EmployeeType;

/** Ruoli della suite e matrice dei permessi sulle risorse dell'applicazione. */
class EmployeeTypeResource extends Resource
{
    use HasPlanAccess;

    protected static ?string $model = EmployeeType::class;

    protected static ?string $slug = 'ruoli';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'Configurazione';

    protected static ?string $navigationLabel = 'Ruoli e permessi';

    protected static ?string $modelLabel = 'Ruolo';

    protected static ?string $pluralModelLabel = 'Ruoli';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 7;

    public static function canViewAny(): bool
    {
        $utente = auth()->user();

        return $utente instanceof User && in_array($utente->role, ['admin', 'super_admin'], true) && static::hasPlanFeatureAccess();
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
        return $schema->components([
            TextEntry::make('name')->label('Ruolo'),
            TextEntry::make('companytype')->label('Tipo azienda'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Ruolo')->searchable()->sortable(),
                TextColumn::make('companytype')->label('Tipo azienda')->sortable(),
                IconColumn::make('is_external')->label('Esterno')->boolean(),
            ])
            ->recordActions([])
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [
            PermissionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmployeeTypes::route('/'),
            'view' => ViewEmployeeType::route('/{record}'),
        ];
    }
}
