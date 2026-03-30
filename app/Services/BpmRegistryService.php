<?php

namespace App\Services;

use App\Contracts\BusinessRule;
use App\Models\ChecklistItem;
use App\Models\TaskExecution;
use Illuminate\Support\Collection;

class BpmRegistryService
{
    /**
     * Restituisce le opzioni filtrate e raggruppate per Filament
     *
     * * @param string $type 'actions' o 'conditions'
     * @param  int  $currentCompanyId  L'ID dell'azienda attualmente loggata
     */
    public static function getOptionsForFilament(string $type, char $currentCompanyId): array
    {
        // 1. Leggiamo tutto l'array dal file di configurazione
        $registry = config("bpm_registry.{$type}", []);

        $options = [];

        foreach ($registry as $classPath => $data) {
            // 2. Controllo Multi-Tenant: Questa classe è permessa a questa azienda?
            $allowedCompanies = $data['companies'];

            if (is_array($allowedCompanies) && ! in_array($currentCompanyId, $allowedCompanies)) {
                continue;  // Salta! L'azienda non ha i permessi per vedere questa azione
            }

            // 3. Raggruppamento (Grouping)
            $groupName = $data['group'] ?? 'Generale';

            // Costruiamo l'array multidimensionale che Filament ama
            $options[$groupName][$classPath] = $data['name'];
        }

        return $options;
    }

    /**
     * Valuta quali checklist item sono applicabili e obbligatori per una specifica esecuzione.
     */
    public function getEvaluatedChecklist(TaskExecution $execution): Collection
    {
        // Recupera gli item legati al task del processo
        $items = $execution
            ->processTask
            ->checklists()
            ->with('items')
            ->get()
            ->pluck('items')
            ->flatten();

        return $items->map(function (ChecklistItem $item) use ($execution) {
            $client = $execution->client;

            // 1. Valuta la Skip Condition (se restituise true, l'item viene annullato)
            if ($item->skip_condition_class && class_exists($item->skip_condition_class)) {
                $skipRule = app($item->skip_condition_class);
                if ($skipRule instanceof BusinessRule && $skipRule->evaluate($client, $execution)) {
                    return null;
                }
            }

            // 2. Valuta la Require Condition (sovrascrive is_mandatory se la regola è true)
            $isMandatory = (bool) $item->is_mandatory;
            if ($item->require_condition_class && class_exists($item->require_condition_class)) {
                $requireRule = app($item->require_condition_class);
                if ($requireRule instanceof BusinessRule && $requireRule->evaluate($client, $execution)) {
                    $isMandatory = true;
                }
            }

            // Restituisce un oggetto arricchito per la UI
            return (object) [
                'id' => $item->id,
                'instruction' => $item->instruction,
                'is_mandatory' => $isMandatory,
                'original_item' => $item,
            ];
        })->filter();  // Rimuove gli elementi nulli (quelli "skippati")
    }
}
