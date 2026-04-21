<?php

namespace App\Filament\Resources\Companies\RelationManagers;

use App\Filament\Resources\Clients\Tables\ClientsTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ClientsRelationManager extends RelationManager
{
    protected static string $relationship = 'companyClients';

    protected static ?string $modelLabel = 'Consulente';

    protected static ?string $pluralModelLabel = 'Consulenti';

    protected static ?string $title = 'Consulenti';

    public function table(Table $table): Table
    {
        return ClientsTable::configure($table)
            ->modifyQueryUsing(function ($query) {
                $query->where('is_company', 1);
            });
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return true;
    }
}
