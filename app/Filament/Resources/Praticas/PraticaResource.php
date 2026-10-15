<?php

namespace App\Filament\Resources\Praticas;

use App\Filament\RelationManagers\ImpegniTerziRelationManager;
use App\Filament\RelationManagers\ProvvigioniRelationManager;
use App\Filament\Resources\Praticas\Pages\CreatePratica;
use App\Filament\Resources\Praticas\Pages\EditPratica;
use App\Filament\Resources\Praticas\Pages\ListPraticas;
use App\Filament\Resources\Praticas\Schemas\PraticaForm;
use App\Filament\Resources\Praticas\Tables\PraticasTable;
use App\Models\Pratica;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Unico\Core\Filament\RelationManagers\DocumentsRelationManager;
use UnitEnum;

class PraticaResource extends Resource
{
    protected static ?string $model = Pratica::class;

    protected static ?string $slug = 'pratiche';

    protected static string|UnitEnum|null $navigationGroup = 'Pratiche';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Pratiche';

    protected static ?string $modelLabel = 'Pratica';

    protected static ?string $pluralModelLabel = 'Pratiche';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $recordTitleAttribute = 'codice_pratica';

    public static function form(Schema $schema): Schema
    {
        return PraticaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PraticasTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            DocumentsRelationManager::class,
            ProvvigioniRelationManager::class,
            ImpegniTerziRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPraticas::route('/'),
            'create' => CreatePratica::route('/create'),
            'edit' => EditPratica::route('/{record}/edit'),
        ];
    }
}
