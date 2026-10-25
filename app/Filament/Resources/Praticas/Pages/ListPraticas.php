<?php

namespace App\Filament\Resources\Praticas\Pages;

use App\Filament\Resources\Praticas\PraticaResource;
use App\Services\ImportAggiornamentiBanca;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;

class ListPraticas extends ListRecords
{
    protected static string $resource = PraticaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->inAttesaDellaBanca(),
            $this->importaAggiornamenti(),
            CreateAction::make(),
        ];
    }

    /** Imposta i filtri sulle pratiche inviate in banca e ancora senza esito: tutti gli istruttori, la banca si sceglie dal filtro. */
    private function inAttesaDellaBanca(): Action
    {
        return Action::make('inAttesaDellaBanca')
            ->label('In attesa della banca')
            ->icon(Heroicon::OutlinedBuildingLibrary)
            ->color('gray')
            ->action(function (): void {
                $this->tableFilters = [
                    'employee_id' => ['value' => null],
                    'inviate_in_banca' => ['isActive' => true],
                    'escludi_respinte' => ['isActive' => true],
                    'non_perfezionate' => ['isActive' => true],
                    'sospese' => ['value' => false],
                ];
                $this->updatedTableFilters();
            });
    }

    private function importaAggiornamenti(): Action
    {
        return Action::make('importaAggiornamenti')
            ->label('Importa stati')
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->color('gray')
            ->modalHeading('Importa gli stati dalla banca')
            ->modalDescription('Carica il file della banca (CSV o XLSX) con almeno il cognome del cedente e lo stato attuale della pratica; facoltativi il codice pratica banca e il tipo di prodotto. La banca si riconosce dal tracciato già memorizzato o dalle pratiche del file; la pratica si trova dal codice banca o dal cognome, il codice banca si salva sulla pratica e lo stato si aggiunge se non esiste.')
            ->modalSubmitActionLabel('Importa')
            ->schema([
                FileUpload::make('file')
                    ->label('File della banca')
                    ->disk('local')
                    ->directory('import-aggiornamenti-banca')
                    ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                    ->required(),
            ])
            ->action(function (array $data): void {
                $percorso = Storage::disk('local')->path($data['file']);

                try {
                    $esito = app(ImportAggiornamentiBanca::class)->importa($percorso);
                } finally {
                    Storage::disk('local')->delete($data['file']);
                }

                $anomalie = $esito['anomalie'];

                $dettagli = array_filter([
                    $esito['banca'] === null ? null : 'Banca: '.$esito['banca'].($esito['tracciato_memorizzato'] ? ' (tracciato memorizzato)' : ''),
                    $esito['codici'] > 0 ? "{$esito['codici']} codici banca salvati" : null,
                    $esito['stati_aggiunti'] !== [] ? 'stati aggiunti da configurare: '.implode(', ', $esito['stati_aggiunti']) : null,
                    $anomalie === [] ? null : count($anomalie).' righe non importate: '.implode('; ', array_slice($anomalie, 0, 5)).(count($anomalie) > 5 ? '…' : ''),
                ]);

                Notification::make()
                    ->title("{$esito['aggiornate']} pratiche aggiornate, {$esito['invariate']} già nello stato indicato")
                    ->body($dettagli === [] ? null : implode('. ', $dettagli))
                    ->color($anomalie === [] ? 'success' : 'warning')
                    ->persistent($anomalie !== [])
                    ->send();
            });
    }
}
