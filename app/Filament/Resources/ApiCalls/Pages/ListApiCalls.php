<?php

namespace App\Filament\Resources\ApiCalls\Pages;

use App\Filament\Resources\ApiCalls\ApiCallResource;
use Filament\Resources\Pages\ListRecords;

class ListApiCalls extends ListRecords
{
    protected static string $resource = ApiCallResource::class;
}
