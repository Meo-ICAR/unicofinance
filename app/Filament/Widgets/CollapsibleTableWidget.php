<?php

namespace App\Filament\Widgets;

use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Tabella dashboard racchiusa in una sezione apribile, chiusa all'inizio. Il titolo resta sulla sezione, con il numero di record. */
abstract class CollapsibleTableWidget extends TableWidget
{
    protected string $view = 'filament.widgets.collapsible-table';

    protected function makeTable(): Table
    {
        return parent::makeTable()->heading(null);
    }

    public function getHeading(): ?string
    {
        return static::$heading;
    }

    /** Titolo della sezione con il numero di record, secondo i filtri applicati. */
    public function getHeadingConConteggio(): ?string
    {
        $titolo = $this->getHeading();

        return $titolo === null ? null : $titolo.' ('.number_format($this->getFilteredTableQuery()->count(), 0, ',', '.').')';
    }
}
