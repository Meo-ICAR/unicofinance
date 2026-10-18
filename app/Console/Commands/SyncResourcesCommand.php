<?php

namespace App\Console\Commands;

use Filament\Facades\Filament;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Unico\Core\Models\Resource as ResourceModel;

class SyncResourcesCommand extends Command
{
    protected $signature = 'permissions:sync-resources';

    protected $description = 'Scansiona le risorse Filament e le sincronizza nel DB per l\'applicazione corrente';

    public function handle(): int
    {
        $appName = config('app.name');
        $this->info("Inizio sincronizzazione risorse per l'applicazione: [ {$appName} ]");

        $count = 0;

        foreach (Filament::getPanels() as $panel) {
            foreach ($panel->getResources() as $resourceClass) {
                try {
                    $key = method_exists($resourceClass, 'getSlug')
                        ? $resourceClass::getSlug()
                        : Str::kebab(class_basename($resourceClass));

                    $name = method_exists($resourceClass, 'getModelLabel')
                        ? Str::title($resourceClass::getModelLabel())
                        : class_basename($resourceClass);

                    $group = method_exists($resourceClass, 'getNavigationGroup')
                        ? $resourceClass::getNavigationGroup()
                        : null;

                    ResourceModel::firstOrCreate(
                        [
                            'app_name' => $appName,
                            'key' => $key,
                        ],
                        [
                            'name' => $name,
                            'group' => $group,
                            'min_plan' => 'BASE',
                        ]
                    );

                    $count++;
                } catch (\Throwable $e) {
                    $this->warn("Impossibile sincronizzare la risorsa {$resourceClass}: ".$e->getMessage());
                }
            }
        }

        $this->info("Sincronizzazione completata! Processate {$count} risorse per {$appName}.");

        return Command::SUCCESS;
    }
}
