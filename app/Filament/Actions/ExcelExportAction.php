<?php

namespace App\Filament\Actions;

use App\Filament\Exports\DynamicGroupExport;
use pxlrbt\FilamentExcel\Actions\ExportAction;

/** Pulsante «Excel» in testata alle tabelle: esporta le colonne visibili con i filtri applicati, come in Proforma. */
class ExcelExportAction extends ExportAction
{
    public static function getDefaultName(): ?string
    {
        return 'excelExport';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Excel')
            ->color('success')
            ->exports([DynamicGroupExport::make()]);
    }

    /**
     * Totali in fondo per le colonne indicate (intestazioni come in tabella), con subtotali per gruppo se c'è `$groupBy`.
     *
     * @param  list<string>  $columns
     */
    public function sumColumns(array $columns, ?string $groupBy = null): static
    {
        $export = DynamicGroupExport::make()->sumColumns($columns);

        return $this->exports([$groupBy === null ? $export : $export->groupBy($groupBy)]);
    }
}
