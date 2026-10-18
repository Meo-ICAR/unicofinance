<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\MostraManuale;
use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class ManualeTecnico extends Page
{
    use MostraManuale;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-code-bracket';

    protected static ?string $navigationLabel = 'Manuale tecnico';

    protected static ?string $title = 'Manuale tecnico';

    protected static ?string $slug = 'manuale-tecnico';

    protected static string|UnitEnum|null $navigationGroup = 'Manuali';

    protected static ?int $navigationSort = 20;

    protected string $view = 'filament.pages.manuale-html';

    protected static function nomeRottaManuale(): string
    {
        return 'manuali.tecnico';
    }
}
