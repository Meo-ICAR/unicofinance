<?php

namespace App\Filament\Resources\EmployeeTypes\Pages;

use App\Filament\Resources\EmployeeTypes\EmployeeTypeResource;
use Filament\Resources\Pages\ListRecords;

class ListEmployeeTypes extends ListRecords
{
    protected static string $resource = EmployeeTypeResource::class;
}
