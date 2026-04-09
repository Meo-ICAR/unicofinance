<?php

namespace App\Filament\Resources\BusinessFunctions\Schemas;

use App\Enums\BusinessFunctionType;
use App\Enums\MacroArea;
use App\Enums\OutsourcableStatus;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class BusinessFunctionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informazioni Generali')
                    ->description('Dettagli principali della funzione aziendale')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('code')
                                ->label('Codice')
                                ->required()
                                ->maxLength(255),

                            TextInput::make('name')
                                ->label('Nome')
                                ->required()
                                ->maxLength(255),

                            Select::make('managed_by_id')
                                ->label('Riporta A (Funzione Superiore)')
                                ->relationship('manager', 'name', modifyQueryUsing: fn ($query) => $query->where('company_id', auth()->user()?->current_company_id))
                                ->searchable()
                                ->preload(),

                            Select::make('macro_area')
                                ->label('Macro Area')
                                ->options(MacroArea::class)
                                ->required(),

                            Select::make('type')
                                ->label('Tipologia')
                                ->options(BusinessFunctionType::class)
                                ->required(),

                            Select::make('outsourcable_status')
                                ->label('Stato Outsourcing')
                                ->options(OutsourcableStatus::class)
                                ->default('no')
                                ->required(),
                        ]),
                    ]),

                Section::make('Dettagli Operativi')
                    ->description('Descrizione, mission e responsabilità primarie')
                    ->schema([
                        Textarea::make('description')
                            ->label('Descrizione')
                            ->rows(3)
                            ->columnSpanFull(),

                        Textarea::make('mission')
                            ->label('Mission')
                            ->rows(4)
                            ->columnSpanFull(),

                        Textarea::make('responsibility')
                            ->label('Responsabilità')
                            ->rows(4)
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                Section::make('Privacy / GDPR')
                    ->description('Dati relativi al trattamento e protezione dei dati personali')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('privacy_role')
                                ->label('Ruolo Privacy'),
                            TextInput::make('retention_period')
                                ->label('Periodo di Conservazione'),
                            TextInput::make('extra_eu_transfer')
                                ->label('Trasferimenti Extra UE'),
                            TextInput::make('privacy_data')
                                ->label('Dati Privacy'),
                        ]),
                        Grid::make(2)->schema([
                            Textarea::make('purpose')
                                ->label('Finalità')
                                ->rows(3),
                            Textarea::make('data_subjects')
                                ->label('Categorie di Interessati')
                                ->rows(3),
                            Textarea::make('data_categories')
                                ->label('Categorie di Dati')
                                ->rows(3),
                            Textarea::make('security_measures')
                                ->label('Misure di Sicurezza')
                                ->rows(3),
                        ]),
                    ])
                    ->collapsible(),
            ]);
    }
}
