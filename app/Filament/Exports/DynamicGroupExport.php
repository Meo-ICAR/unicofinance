<?php

namespace App\Filament\Exports;

use App\Support\TestoExcelSicuro;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use Unico\Core\Filament\Concerns\InjectsRecordByTypeInExports;

/**
 * Esporta in Excel le colonne della tabella (filtri compresi), come in Proforma: in cima i filtri applicati, in fondo i totali
 * delle colonne indicate in `sumColumns()`, con subtotali per gruppo se c'è `groupBy()`.
 */
class DynamicGroupExport extends ExcelExport implements WithCustomValueBinder, WithEvents
{
    use InjectsRecordByTypeInExports;

    protected ?string $groupBy = null;

    /** @var list<string> */
    protected array $sumColumns = [];

    const FORMAT_CURRENCY = '#,##0.00" €"';

    public static function make(?string $name = null): static
    {
        return parent::make($name ?? 'export')
            ->fromTable()
            ->withFilename('report_'.now()->format('Y-m-d_H-i'));
    }

    public function groupBy(string $column): static
    {
        $this->groupBy = $column;

        return $this;
    }

    /** @param  list<string>  $columns  intestazioni delle colonne da sommare */
    public function sumColumns(array $columns): static
    {
        $this->sumColumns = $columns;

        return $this;
    }

    /** I testi che iniziano con «=» restano testo (vedi TestoExcelSicuro). */
    public function bindValue(Cell $cell, mixed $value): bool
    {
        return (new TestoExcelSicuro)->bindValue($cell, $value);
    }

    /** @return array<class-string, callable> */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $this->processSheet($event);
            },
        ];
    }

    protected function processSheet(AfterSheet $event): void
    {
        $sheet = $event->sheet->getDelegate();

        $filterStrings = [];
        foreach ($this->getLivewire()->tableFilters ?? [] as $name => $data) {
            $value = $this->describeFilter($data);
            if ($value !== '') {
                $filterStrings[] = strtoupper($name).': '.$value;
            }
        }
        $filtersText = 'FILTRI APPLICATI: '.(empty($filterStrings) ? 'Nessuno' : implode(' | ', $filterStrings));

        $sheet->insertNewRowBefore(1, 2);
        $sheet->setCellValue('A1', $filtersText);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setItalic(true)->getColor()->setARGB('FF555555');

        $headerRow = 3;
        $highestColumn = $sheet->getHighestColumn();
        $sheet->getStyle("A{$headerRow}:{$highestColumn}{$headerRow}")->getFont()->setBold(true);

        $highestRow = $sheet->getHighestRow();
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

        if ($highestRow <= $headerRow || $this->sumColumns === []) {
            return;
        }

        $headings = $sheet->rangeToArray("A{$headerRow}:{$highestColumn}{$headerRow}", null, true, false)[0];
        $headingsLower = array_map(fn ($heading): string => strtolower(trim((string) $heading)), $headings);

        $sumIndices = [];
        foreach ($this->sumColumns as $column) {
            $index = array_search(strtolower($column), $headingsLower, true);
            if ($index !== false) {
                $sumIndices[] = $index;
            }
        }

        $dataStartRow = $headerRow + 1;
        $rows = $sheet->rangeToArray("A{$dataStartRow}:{$highestColumn}{$highestRow}", null, true, false);

        $groupByIndex = $this->groupBy === null ? false : array_search(strtolower($this->groupBy), $headingsLower, true);

        if ($groupByIndex !== false) {
            $groups = [];
            foreach ($rows as $row) {
                $groups[$row[$groupByIndex] ?? ''][] = $row;
            }

            $sheet->removeRow($dataStartRow, $highestRow - $headerRow);

            $grandTotals = array_fill_keys($sumIndices, 0);
            $currentRow = $dataStartRow;

            foreach ($groups as $groupName => $groupRows) {
                $groupSums = array_fill_keys($sumIndices, 0);

                foreach ($groupRows as $row) {
                    foreach ($row as $columnIndex => $value) {
                        $sheet->setCellValue(Coordinate::stringFromColumnIndex($columnIndex + 1).$currentRow, $value);
                    }
                    foreach ($sumIndices as $index) {
                        $groupSums[$index] += $this->parseNumericValue($row[$index] ?? 0);
                    }
                    $currentRow++;
                }

                foreach ($sumIndices as $index) {
                    $grandTotals[$index] += $groupSums[$index];
                }

                $this->writeTotalRow($sheet, $currentRow, $groupByIndex, 'TOTALE '.strtoupper((string) $groupName), $groupSums, $sumIndices, $highestColumnIndex, false);
                $currentRow += 2;
            }

            $this->writeTotalRow($sheet, $currentRow, $groupByIndex, 'GRAN TOTALE COMPLESSIVO', $grandTotals, $sumIndices, $highestColumnIndex, true);

            return;
        }

        $grandTotals = array_fill_keys($sumIndices, 0);
        foreach ($rows as $row) {
            foreach ($sumIndices as $index) {
                $grandTotals[$index] += $this->parseNumericValue($row[$index] ?? 0);
            }
        }

        $this->writeTotalRow($sheet, $highestRow + 1, 0, 'GRAN TOTALE COMPLESSIVO', $grandTotals, $sumIndices, $highestColumnIndex, true);
    }

    /**
     * Il valore di un filtro: `value`/`values` per quelli semplici, «campo valore» per quelli con più campi (date da/a...).
     */
    protected function describeFilter(mixed $data): string
    {
        if (! is_array($data)) {
            return is_scalar($data) ? trim((string) $data) : '';
        }

        if (array_key_exists('value', $data) || array_key_exists('values', $data)) {
            $value = $data['value'] ?? $data['values'] ?? null;
            if (empty($value)) {
                return '';
            }

            return is_array($value) ? implode(', ', array_filter($value, fn ($item): bool => filled($item))) : trim((string) $value);
        }

        if (array_key_exists('isActive', $data)) {
            return $data['isActive'] ? 'sì' : '';
        }

        $parts = [];
        foreach ($data as $field => $value) {
            if (is_scalar($value) && filled($value)) {
                $parts[] = $field.' '.$value;
            }
        }

        return implode(', ', $parts);
    }

    /** Importi formattati all'italiana ("1.234,56 €") tornano numeri. */
    protected function parseNumericValue(mixed $value): float
    {
        if (is_string($value)) {
            $value = str_replace(['€', '.', ' ', "\u{00A0}"], '', $value);
            $value = str_replace(',', '.', $value);
        }

        return (float) $value;
    }

    /**
     * @param  array<int, float>  $totals
     * @param  list<int>  $sumIndices
     */
    protected function writeTotalRow(Worksheet $sheet, int $rowIndex, int $labelColumnIndex, string $label, array $totals, array $sumIndices, int $maxColumn, bool $isGrandTotal): void
    {
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($labelColumnIndex + 1).$rowIndex, $label);

        $style = $sheet->getStyle('A'.$rowIndex.':'.Coordinate::stringFromColumnIndex($maxColumn).$rowIndex);
        $style->getFont()->setBold(true);

        if ($isGrandTotal) {
            $style->getFont()->setSize(12);
            $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD9D9D9');
        }

        foreach ($sumIndices as $index) {
            $cell = Coordinate::stringFromColumnIndex($index + 1).$rowIndex;
            $sheet->setCellValue($cell, $totals[$index]);
            $sheet->getStyle($cell)->getNumberFormat()->setFormatCode(self::FORMAT_CURRENCY);
        }
    }
}
