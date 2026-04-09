<?php

namespace App\Filament\Resources\RequestRegistries\Pages;

use App\Filament\Resources\RequestRegistries\RequestRegistryResource;
use App\Models\RequestRegistry;
use Filament\Resources\Pages\CreateRecord;

class CreateRequestRegistry extends CreateRecord
{
    protected static string $resource = RequestRegistryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['company_id'] = auth()->user()->company_id ?? \App\Models\Company::first()?->id;

        return $data;
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Richiesta registrata nel registro';
    }
}
