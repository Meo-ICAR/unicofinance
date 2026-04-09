<?php

namespace App\Filament\Resources\Clients\Schemas;

use App\Models\Client;
use Filament\Actions\Action;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Infolists\Components\RepeatableEntry;
use Barryvdh\DomPDF\Facade\Pdf;

class ClientInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('NOMINA A RESPONSABILE ESTERNO DEL TRATTAMENTO')
                    ->description('Art. 28 Regolamento UE 2016/679')
                    ->headerActions([
                        Action::make('download_pdf')
                            ->label('Scarica PDF')
                            ->icon('heroicon-o-arrow-down-tray')
                            ->color('primary')
                            ->action(function (Client $record) {
                                $pdf = Pdf::loadView('pdf.client-nomination', ['client' => $record]);
                                return response()->streamDownload(fn () => print($pdf->output()), "Nomina_Esterna_{$record->name}.pdf");
                            }),
                    ])
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('company.name')
                                ->label('Titolare del Trattamento')
                                ->weight('bold'),
                            TextEntry::make('name')
                                ->label('Responsabile Esterno (Processore)')
                                ->weight('bold'),
                        ]),

                        TextEntry::make('tax_code')
                            ->label('Codice Fiscale / P.IVA')
                            ->placeholder('N/D'),

                        RepeatableEntry::make('businessFunctions')
                            ->label('Ambiti di trattamento affidati')
                            ->schema([
                                Section::make(fn ($record) => $record->name)
                                    ->compact()
                                    ->schema([
                                        TextEntry::make('purpose')
                                            ->label('Finalità del trattamento esternalizzato'),
                                        TextEntry::make('data_categories')
                                            ->label('Categorie di dati trattati'),
                                        TextEntry::make('security_measures')
                                            ->label('Requisiti e misure di sicurezza richieste')
                                            ->prose(),
                                    ])
                            ])->columns(1),
                    ]),

                Section::make('Sottoscrizione per Accettazione')
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Data di nomina')
                            ->dateTime('d/m/Y'),
                        TextEntry::make('firma_titolare')
                            ->default('___________________________')
                            ->label('Firma del Titolare (Data Controller)'),
                        TextEntry::make('firma_responsabile')
                            ->default('___________________________')
                            ->label('Firma del Responsabile (Data Processor)'),
                    ])->columns(3)
            ]);
    }
}
