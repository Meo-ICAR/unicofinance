<?php

namespace App\Filament\Widgets;

use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Tabella dashboard racchiusa in una sezione apribile. Il titolo resta sulla sezione. */
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
}
