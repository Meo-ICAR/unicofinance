<?php

namespace App\Filament\Widgets;

use App\Models\TaskExecution;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MasterBrokerStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Pratiche Fuori SLA', TaskExecution::where('is_overdue', true)->count())
                ->description('Intervento necessario')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger'),
            Stat::make('In Attesa Documenti', TaskExecution::whereHas('checklistItems', fn ($q) => $q->where('requires_revision', true))->count())
                ->description('Solleciti automatici inviati')
                ->color('warning'),
            Stat::make('Conformità OAM (Mese)', '99.8%')
                ->description('Regole di compliance rispettate')
                ->descriptionIcon('heroicon-m-shield-check')
                ->color('success'),
        ];
    }
}
