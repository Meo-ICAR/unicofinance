<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Filament\Resources\Clients\ConsultantResource;
use Filament\Resources\Pages\ListRecords;

class ListConsultants extends ListRecords
{
    protected static string $resource = ConsultantResource::class;
}
