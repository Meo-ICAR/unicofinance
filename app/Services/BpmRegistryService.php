<?php

namespace App\Services;

class BpmRegistryService
{
    /**
     * Restituisce le opzioni filtrate e raggruppate per Filament
     * * @param string $type 'actions' o 'conditions'
     * @param int $currentCompanyId L'ID dell'azienda attualmente loggata
     * @return array
     */
    public static function getOptionsForFilament(string $type, char $currentCompanyId): array
    {
        // 1. Leggiamo tutto l'array dal file di configurazione
        $registry = config("bpm_registry.{$type}", []);

        $options = [];

        foreach ($registry as $classPath => $data) {
            // 2. Controllo Multi-Tenant: Questa classe è permessa a questa azienda?
            $allowedCompanies = $data['companies'];

            if (is_array($allowedCompanies) && !in_array($currentCompanyId, $allowedCompanies)) {
                continue;  // Salta! L'azienda non ha i permessi per vedere questa azione
            }

            // 3. Raggruppamento (Grouping)
            $groupName = $data['group'] ?? 'Generale';

            // Costruiamo l'array multidimensionale che Filament ama
            $options[$groupName][$classPath] = $data['name'];
        }

        return $options;
    }
}
