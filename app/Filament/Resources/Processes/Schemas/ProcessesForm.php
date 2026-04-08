<?php

namespace App\Filament\Resources\Processes\Schemas;

use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use \Illuminate\Database\Eloquent\Builder;

class ProcessesForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informazioni Processo')
                    ->description('Dettagli principali del processo aziendale')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name')
                                ->label('Nome Processo')
                                ->required()
                                ->maxLength(255)
                                ->columnSpanFull(),

                            Textarea::make('description')
                                ->label('Descrizione')
                                ->maxLength(65535)
                                ->columnSpanFull(),

                            Select::make('business_function_id')
                                ->relationship('businessFunction', 'name').
                              //  ->required(),

                            Select::make('owner_function_id')
                                  ->relationship('ownerFunction', 'name').

                            Select::make('target_model')
                                ->label('Modello Target')
                                ->options(function () {
                                    $models = [];
                                    foreach (glob(app_path('Models/*.php')) as $file) {
                                        $model = basename($file, '.php');
                                        $models["App\\Models\\{$model}"] = preg_replace('/(?<!^)[A-Z]/', ' $0', $model);
                                    }
                                    return $models;
                                })
                                ->searchable()
                                ->placeholder('Seleziona un modello'),

                            Toggle::make('is_active')
                                ->label('Attivo')
                                ->default(true)
                                ->inline(false),

                            Hidden::make('company_id')
                                ->default(fn() => auth()->user()?->current_company_id)
                                ->required(),
                        ]),
                    ]),
            ]);
    }
}
