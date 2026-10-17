<?php

namespace App\Filament\Resources\DatoriDiLavoro\RelationManagers;

use App\Filament\RelationManagers\SociRelationManager;

/** Per un datore di lavoro i soggetti collegati (dipendenti, referenti, rappresentanti) sono il suo organigramma. */
class OrganigrammaRelationManager extends SociRelationManager
{
    protected static ?string $title = 'Organigramma';

    protected static ?string $modelLabel = 'Persona';

    protected static ?string $pluralModelLabel = 'Organigramma';
}
