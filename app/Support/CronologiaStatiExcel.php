<?php

namespace App\Support;

use App\Models\Pratica;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** La cronologia stati della pratica (pratica_status_history) in Excel, con le stesse informazioni del pannello «Cronologia stati». */
class CronologiaStatiExcel
{
    private const INTESTAZIONI = ['Data', 'Da stato', 'A stato', 'Eseguito da', 'Origine', 'Visto da', 'Visto il', 'Note'];

    public static function scarica(Pratica $pratica): StreamedResponse
    {
        $righe = $pratica->praticaStatusHistory()
            ->with(['employee', 'user', 'seenBy'])
            ->orderByDesc('changed_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn ($voce): array => [
                $voce->changed_at?->format('d/m/Y H:i'),
                $voce->status_from,
                $voce->status_to,
                $voce->employee?->name ?? $voce->user?->name,
                $voce->source,
                $voce->seenBy?->name,
                $voce->seen_at?->format('d/m/Y H:i'),
                $voce->notes,
            ])
            ->all();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Cronologia stati');
        $sheet->setCellValue('A1', 'Pratica '.$pratica->codice_pratica);
        $sheet->getStyle('A1')->getFont()->setBold(true);
        $sheet->fromArray(self::INTESTAZIONI, null, 'A3');
        $sheet->getStyle('A3:H3')->getFont()->setBold(true);
        $sheet->fromArray($righe, null, 'A4');
        foreach (range('A', 'H') as $colonna) {
            $sheet->getColumnDimension($colonna)->setAutoSize(true);
        }

        $nome = 'cronologia_stati_'.str($pratica->codice_pratica ?: (string) $pratica->getKey())->slug('_').'_'.now()->format('Y-m-d_H-i').'.xlsx';

        return response()->streamDownload(fn () => (new Xlsx($spreadsheet))->save('php://output'), $nome, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
