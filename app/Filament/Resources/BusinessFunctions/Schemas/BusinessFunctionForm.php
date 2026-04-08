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
            ]);
    }
}
