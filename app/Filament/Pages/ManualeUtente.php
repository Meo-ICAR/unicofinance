<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\MostraManuale;
use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class ManualeUtente extends Page
{
    use MostraManuale;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationLabel = 'Manuale utente';

    protected static ?string $title = 'Manuale utente';

    protected static ?string $slug = 'manuale-utente';

    protected static string|UnitEnum|null $navigationGroup = 'Manuali';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.manuale-html';

    protected static function nomeRottaManuale(): string
    {
        return 'manuali.utente';
    }
}
