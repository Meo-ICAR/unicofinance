<?php

namespace App\Filament\Agenti\Widgets;

use App\Enums\MaturazioneProvvigione;
use Filament\Schemas\Components\Component;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Sunto delle provvigioni dell'agente per stadio, senza le pratiche declinate o sospese. */
class SuntoProvvigioniAgente extends StatsOverviewWidget
{
    protected static ?int $sort = 4;

    protected ?string $heading = 'Provvigioni';

    public function getSectionContentComponent(): Component
    {
        return parent::getSectionContentComponent()->collapsible();
    }

    /** @return array<int, Stat> */
    protected function getStats(): array
    {
        return collect(MaturazioneProvvigione::cases())->map(function (MaturazioneProvvigione $stadio): Stat {
            $query = $stadio->applica(ProvvigioniAgente::base());
            $totale = (float) $query->sum('importo');
            $pratiche = (clone $query)->distinct()->count('id_pratica');

            return Stat::make($stadio->getLabel(), '€ '.number_format($totale, 2, ',', '.'))
                ->description($pratiche.' pratiche')
                ->color($stadio->getColor());
        })->all();
    }
}
