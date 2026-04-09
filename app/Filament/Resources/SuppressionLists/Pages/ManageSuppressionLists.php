<?php

namespace App\Filament\Resources\SuppressionLists\Pages;

use App\Filament\Resources\SuppressionLists\SuppressionListResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSuppressionLists extends ManageRecords
{
    protected static string $resource = SuppressionListResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
