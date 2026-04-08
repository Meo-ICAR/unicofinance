<?php

namespace App\Filament\Resources\ProcessTaskPrivacyData;

use App\Filament\Resources\ProcessTaskPrivacyData\Pages\CreateProcessTaskPrivacyData;
use App\Filament\Resources\ProcessTaskPrivacyData\Pages\EditProcessTaskPrivacyData;
use App\Filament\Resources\ProcessTaskPrivacyData\Pages\ListProcessTaskPrivacyData;
use App\Filament\Resources\ProcessTaskPrivacyData\Schemas\ProcessTaskPrivacyDataForm;
use App\Filament\Resources\ProcessTaskPrivacyData\Tables\ProcessTaskPrivacyDataTable;
use App\Models\ProcessTaskPrivacyData;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProcessTaskPrivacyDataResource extends Resource
{
    protected static ?string $model = ProcessTaskPrivacyData::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ProcessTaskPrivacyDataForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProcessTaskPrivacyDataTable::configure($table);
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
            'index' => ListProcessTaskPrivacyData::route('/'),
            'create' => CreateProcessTaskPrivacyData::route('/create'),
            'edit' => EditProcessTaskPrivacyData::route('/{record}/edit'),
        ];
    }
}
