<?php

namespace App\Filament\Resources\Employees\Schemas;

use App\Models\Employee;
use Filament\Actions\Action;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Infolists\Components\RepeatableEntry;
use Barryvdh\DomPDF\Facade\Pdf;

class EmployeeInfolist
{
    public static function configure(Schema $schema): Schema
    {

     return $schema
        ->schema([
            Section::make('ATTO DI NOMINA A SOGGETTO AUTORIZZATO')
                ->description('Art. 29 Regolamento UE 2016/679')
                ->headerActions([
                    Action::make('download_pdf')
                        ->label('Scarica PDF')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('primary')
                        ->action(function (Employee $record) {
                            $pdf = Pdf::loadView('pdf.employee-nomination', ['employee' => $record]);
                            return response()->streamDownload(fn () => print($pdf->output()), "Nomina_{$record->name}.pdf");
                        }),
                ])
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('company.name')
                            ->label('Titolare del Trattamento')
                            ->weight('bold'),
                        TextEntry::make('name')
                            ->label('Soggetto Autorizzato')
                            ->weight('bold'),
                    ]),

                    TextEntry::make('role_title')
                        ->label('Ruolo Aziendale')
                        ->prefix('In qualità di '),

                    // Visualizzazione delle funzioni come paragrafi del documento
                    RepeatableEntry::make('businessFunctions')
                        ->label('Ambiti di trattamento autorizzati')
                        ->schema([
                            Section::make(fn ($record) => $record->name)
                                ->compact()
                                ->schema([
                                    TextEntry::make('purpose')
                                        ->label('Finalità del trattamento'),
                                    TextEntry::make('data_categories')
                                        ->label('Categorie di dati trattati'),
                                    TextEntry::make('security_measures')
                                        ->label('Istruzioni operative e misure di sicurezza')
                                        ->prose(), // Ottimizza la lettura di testi lunghi
                                ])
                        ])->columns(1),
                ]),

            Section::make('Sottoscrizione')
                ->schema([
                    TextEntry::make('created_at')
                        ->label('Data di emissione')
                        ->dateTime('d/m/Y'),
                    TextEntry::make('firma_titolare')
                        ->default('___________________________')
                        ->label('Firma del Titolare'),
                    TextEntry::make('firma_dipendente')
                        ->default('___________________________')
                        ->label('Firma per accettazione'),
                ])->columns(3)
        ]);
}
}
