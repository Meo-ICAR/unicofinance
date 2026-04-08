<?php

namespace App\Filament\Resources\PrivacyLegalBases;

use App\Filament\Resources\PrivacyLegalBases\Pages\CreatePrivacyLegalBase;
use App\Filament\Resources\PrivacyLegalBases\Pages\EditPrivacyLegalBase;
use App\Filament\Resources\PrivacyLegalBases\Pages\ListPrivacyLegalBases;
use App\Filament\Resources\PrivacyLegalBases\Schemas\PrivacyLegalBaseForm;
use App\Filament\Resources\PrivacyLegalBases\Tables\PrivacyLegalBasesTable;
use App\Models\PrivacyLegalBase;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PrivacyLegalBaseResource extends Resource
{
    protected static ?string $model = PrivacyLegalBase::class;

   protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';


    protected static ?string $navigationLabel = 'Basi Giuridiche';

     protected static string|UnitEnum|null $navigationGroup = 'Privacy';



    public static function form(Schema $schema): Schema
    {
        return PrivacyLegalBaseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PrivacyLegalBasesTable::configure($table);
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
            'index' => ListPrivacyLegalBases::route('/'),
            'create' => CreatePrivacyLegalBase::route('/create'),
            'edit' => EditPrivacyLegalBase::route('/{record}/edit'),
        ];
    }
}
