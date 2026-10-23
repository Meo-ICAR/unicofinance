<?php

namespace App\Filament\Resources\DatoriDiLavoro;

use App\Filament\Actions\ExcelExportAction;
use App\Filament\RelationManagers\AddressesRelationManager;
use App\Filament\RelationManagers\BranchesRelationManager;
use App\Filament\Resources\Clients\Schemas\ClientForm;
use App\Filament\Resources\DatoriDiLavoro\Pages\EditDatoreDiLavoro;
use App\Filament\Resources\DatoriDiLavoro\Pages\ListDatoriDiLavoro;
use App\Filament\Resources\DatoriDiLavoro\RelationManagers\OrganigrammaRelationManager;
use App\Filament\Traits\HasPlanAccess;
use App\Models\Client;
use App\Models\ClientRelation;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Unico\Core\Filament\RelationManagers\DocumentsRelationManager;
use UnitEnum;

/**
 * Datori di lavoro: i clienti che in `client_relations` hanno almeno una persona con ruolo dipendente. Sono clienti a tutti gli effetti
 * (la scheda è quella del cliente, ridotta) ma si lavorano a parte: la maggior parte delle pratiche è cessione o delega. Non si creano
 * da qui: nascono indicando il datore sul dipendente.
 */
class DatoreDiLavoroResource extends Resource
{
    use HasPlanAccess;

    protected static ?string $model = Client::class;

    protected static ?string $slug = 'datori-di-lavoro';

    protected static string|UnitEnum|null $navigationGroup = 'Pratiche';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Datori di lavoro';

    protected static ?string $modelLabel = 'Datore di lavoro';

    protected static ?string $pluralModelLabel = 'Datori di lavoro';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->datoriDiLavoro();
    }

    public static function form(Schema $schema): Schema
    {
        return ClientForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->headerActions([ExcelExportAction::make()])
            ->columns([
                TextColumn::make('name')->label('Datore di lavoro')->searchable()->sortable(),
                TextColumn::make('tax_code')->label('Codice fiscale / P.IVA')->searchable(),
                TextColumn::make('employment_typology')->label('Tipo amministrazione')->searchable()->placeholder('—'),
                TextColumn::make('dipendenti')
                    ->label('Dipendenti')
                    ->state(fn (Client $record): int => ClientRelation::query()->where('company_id', $record->getKey())->where('role', ClientRelation::RUOLO_DIPENDENTE)->count())
                    ->alignEnd(),
                IconColumn::make('richiesta_benestare')
                    ->label('Benestare')
                    ->state(fn (Client $record): bool => filled($record->richiesta_benestare))
                    ->boolean()
                    ->tooltip('Indicato come si chiede il benestare'),
                TextColumn::make('email')->label('Email')->searchable()->placeholder('—'),
                TextColumn::make('pec')->label('PEC')->searchable()->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->recordActions([EditAction::make()]);
    }

    public static function getRelations(): array
    {
        return [
            DocumentsRelationManager::class,
            OrganigrammaRelationManager::class,
            AddressesRelationManager::class,
            BranchesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDatoriDiLavoro::route('/'),
            'edit' => EditDatoreDiLavoro::route('/{record}/edit'),
        ];
    }
}
