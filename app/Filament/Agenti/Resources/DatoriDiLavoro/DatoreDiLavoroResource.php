<?php

namespace App\Filament\Agenti\Resources\DatoriDiLavoro;

use App\Filament\Actions\ExcelExportAction;
use App\Filament\Agenti\Resources\DatoriDiLavoro\Pages\ListDatoriDiLavoro;
use App\Filament\Agenti\Resources\DatoriDiLavoro\Pages\ViewDatoreDiLavoro;
use App\Filament\Agenti\Resources\DatoriDiLavoro\RelationManagers\DipendentiRelationManager;
use App\Filament\Traits\HasPlanAccess;
use App\Models\Client;
use App\Models\ClientRelation;
use App\Services\Agenti\ClientiDellAgente;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Datori di lavoro dei clienti del produttore (cessione e delega), in sola lettura: l'ente, come si chiede il benestare e quali dei
 * suoi clienti ci lavorano. Non vede i dipendenti degli altri produttori.
 */
class DatoreDiLavoroResource extends Resource
{
    use HasPlanAccess;

    protected static ?string $model = Client::class;

    protected static ?string $slug = 'datori-di-lavoro';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $navigationLabel = 'Datori di lavoro';

    protected static ?string $modelLabel = 'Datore di lavoro';

    protected static ?string $pluralModelLabel = 'Datori di lavoro';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 3;

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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereIn('id', app(ClientiDellAgente::class)->datoriIds());
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('name')->label('Datore di lavoro'),
            TextEntry::make('tax_code')->label('Codice fiscale / P.IVA')->placeholder('—'),
            TextEntry::make('employment_typology')->label('Tipo amministrazione')->placeholder('—'),
            TextEntry::make('email')->label('Email')->placeholder('—'),
            TextEntry::make('pec')->label('PEC')->placeholder('—'),
            TextEntry::make('phone')->label('Telefono')->placeholder('—'),
            TextEntry::make('richiesta_benestare')->label('Richiesta benestare')->placeholder('—')->columnSpanFull(),
            TextEntry::make('ritiro_benestare')->label('Ritiro benestare')->placeholder('—')->columnSpanFull(),
            TextEntry::make('notifica')->label('Notifica')->placeholder('—')->columnSpanFull(),
        ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->headerActions([ExcelExportAction::make()])
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Datore di lavoro')->searchable()->sortable(),
                TextColumn::make('tax_code')->label('Codice fiscale / P.IVA')->searchable(),
                TextColumn::make('employment_typology')->label('Tipo amministrazione')->placeholder('—'),
                TextColumn::make('dipendenti')
                    ->label('I tuoi clienti')
                    ->state(fn (Client $record): int => ClientRelation::query()->where('company_id', $record->getKey())->where('role', ClientRelation::RUOLO_DIPENDENTE)->whereIn('client_id', app(ClientiDellAgente::class)->ids())->count())
                    ->alignEnd(),
            ])
            ->recordUrl(fn (Client $record): string => static::getUrl('view', ['record' => $record]));
    }

    public static function getRelations(): array
    {
        return [DipendentiRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDatoriDiLavoro::route('/'),
            'view' => ViewDatoreDiLavoro::route('/{record}'),
        ];
    }
}
