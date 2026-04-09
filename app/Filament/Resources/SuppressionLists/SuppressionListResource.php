<?php

namespace App\Filament\Resources\SuppressionLists;

use App\Filament\Resources\SuppressionLists\Pages\ManageSuppressionLists;
use App\Models\SuppressionList;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SuppressionListResource extends Resource
{
    protected static ?string $model = SuppressionList::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('hashed_identifier')
                    ->required(),
                Select::make('identifier_type')
                    ->options(['email' => 'Email', 'phone' => 'Phone'])
                    ->default('email')
                    ->required(),
                DatePicker::make('request_date')
                    ->required(),
                Toggle::make('do_not_contact')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('hashed_identifier')
                    ->searchable(),
                TextColumn::make('identifier_type')
                    ->badge(),
                TextColumn::make('request_date')
                    ->date()
                    ->sortable(),
                IconColumn::make('do_not_contact')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSuppressionLists::route('/'),
        ];
    }
}
