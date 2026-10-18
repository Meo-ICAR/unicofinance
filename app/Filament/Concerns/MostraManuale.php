<?php

namespace App\Filament\Concerns;

use Filament\Actions\Action;

trait MostraManuale
{
    abstract protected static function nomeRottaManuale(): string;

    public function urlManuale(): string
    {
        return route(static::nomeRottaManuale());
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('stampa')
                ->label('Apri per la stampa')
                ->icon('heroicon-o-printer')
                ->url(fn (): string => $this->urlManuale())
                ->openUrlInNewTab(),
        ];
    }
}
