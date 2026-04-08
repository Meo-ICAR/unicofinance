<?php

namespace App\Filament\Resources\RaciAssignments\Pages;

use App\Filament\Resources\RaciAssignments\RaciAssignmentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions\ReplicateAction;

class EditRaciAssignment extends EditRecord
{
    protected static string $resource = RaciAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
             ReplicateAction::make(),
             /*
                    ->successRedirectUrl(fn (RaciAssignment $replica): string => RaciAssignmentResource::getUrl('edit', $replica))
                    ->excludeAttributes(['business_function_id', 'role']),*/
        ];
    }
}
