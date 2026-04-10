<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use BackedEnum;

class BpmTaskRunner extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-play-circle';

    protected string $view = 'filament.pages.bpm-task-runner';

    protected static bool $shouldRegisterNavigation = false;

    public ?int $executionId = null;

    public function mount(?int $execution = null): void
    {
        $this->executionId = $execution;
    }

    public function getTitle(): string
    {
        return 'BPM Task Runner';
    }
}
