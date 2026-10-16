<?php

namespace App\Filament\Resources\ProvvigioniAttive\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Unico\Core\Models\Cliente;
use Unico\Core\Models\TipoProdotto;

class ProvvigioneAttivaForm
{
    /** Basi di calcolo ammesse da `provvigioni_rules.tipo_provvigioni`. */
    public const BASI = [
        'lordo' => 'Lordo (rata × numero rate)',
        'erogato' => 'Erogato',
        'netto' => 'Netto',
        'interessi' => 'Interessi',
        'premio' => 'Premio (polizze)',
        'fixed' => 'Importo fisso (€)',
    ];

    /** @param  bool  $conBanca  false quando la banca è quella della scheda che contiene l'elenco */
    public static function configure(Schema $schema, bool $conBanca = true): Schema
    {
        return $schema->components([
            Grid::make(2)->schema([
                Select::make('tipoprodotto_id')
                    ->label('Tipo prodotto')
                    ->options(fn (): array => TipoProdotto::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->placeholder('Tutti i prodotti'),
                Select::make('clienti_id')
                    ->label('Banca')
                    ->options(fn (): array => Cliente::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->placeholder('Tutte le banche')
                    ->visible($conBanca),
                Select::make('tipo_provvigioni')
                    ->label('Base di calcolo')
                    ->options(self::BASI)
                    ->required()
                    ->default('lordo'),
                TextInput::make('value')
                    ->label('Valore')
                    ->numeric()
                    ->minValue(0)
                    ->helperText('Percentuale, o euro per l\'importo fisso. Vuoto = libera (concordata sulla pratica).'),
                TextInput::make('ltv_min')->label('LTV da (%)')->numeric()->minValue(0)->maxValue(100)->helperText('Solo mutui: fascia di LTV.'),
                TextInput::make('ltv_max')->label('LTV fino a (%)')->numeric()->minValue(0)->maxValue(100)->gte('ltv_min'),
                DatePicker::make('valid_from')->label('Valida dal'),
                DatePicker::make('valid_to')->label('Valida fino al')->afterOrEqual('valid_from'),
            ]),
            Textarea::make('notes')->label('Note')->columnSpanFull(),
        ]);
    }
}
