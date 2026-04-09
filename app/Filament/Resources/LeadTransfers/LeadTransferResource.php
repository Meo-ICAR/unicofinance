<?php

namespace App\Filament\Resources\LeadTransfers;

use App\Filament\Resources\LeadTransfers\Pages\ManageLeadTransfers;
use App\Models\LeadTransfer;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LeadTransferResource extends Resource
{
    protected static ?string $model = LeadTransfer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('lead_id')
                    ->required()
                    ->numeric(),
                TextInput::make('purchaser_id')
                    ->required()
                    ->numeric(),
                DateTimePicker::make('transferred_at')
                    ->required(),
                TextInput::make('price')
                    ->numeric()
                    ->prefix('$'),
                Select::make('transfer_method')
                    ->options(['api_tls' => 'Api tls', 'sftp' => 'Sftp', 'encrypted_csv' => 'Encrypted csv'])
                    ->default('api_tls')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('lead_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('purchaser_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('transferred_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('price')
                    ->money()
                    ->sortable(),
                TextColumn::make('transfer_method')
                    ->badge(),
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
            'index' => ManageLeadTransfers::route('/'),
        ];
    }
}
