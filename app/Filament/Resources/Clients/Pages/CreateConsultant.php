<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Filament\Resources\Clients\ConsultantResource;
use Filament\Resources\Pages\CreateRecord;

class CreateConsultant extends CreateRecord
{
    protected static string $resource = ConsultantResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Assicura che i nuovi record creati da questo resource siano sempre consulenti
        $data['is_company'] = true;

        return $data;
    }
}
