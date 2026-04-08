<?php

namespace App\Filament\Resources\ChecklistItems\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ChecklistItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Textarea::make('instruction')
                    ->required()
                    ->columnSpanFull(),
                Toggle::make('is_mandatory')
                    ->required(),

                 Select::make('require_condition_class')
                                ->label('Regola di Richiesta')
                                ->options(function () {
                                    $rules = [];
                                    foreach (glob(app_path('Rules/*.php')) as $file) {
                                        $rule = basename($file, '.php');
                                        $rules["App\\Rules\\{$rule}"] = preg_replace('/(?<!^)[A-Z]/', ' $0', $rule);
                                    }
                                    return $rules;
                                })
                                ->searchable()
                                ->placeholder('Seleziona una regola'),
                 Select::make('skip_condition_class')
                                ->label('Regola di Salto')
                                ->options(function () {
                                    $rules = [];
                                    foreach (glob(app_path('Rules/*.php')) as $file) {
                                        $rule = basename($file, '.php');
                                        $rules["App\\Rules\\{$rule}"] = preg_replace('/(?<!^)[A-Z]/', ' $0', $rule);
                                    }
                                    return $rules;
                                })
                                ->searchable()
                                ->placeholder('Seleziona una regola'),
                TextInput::make('sort_order')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
