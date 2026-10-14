<?php

namespace Database\Seeders;

use App\Services\Preventivazione\ProductTaskDocuments;
use Illuminate\Database\Seeder;
use Unico\Core\Models\DocumentRequirement;
use Unico\Core\Models\DocumentType;
use Unico\Core\Models\Task;

/**
 * Documenti del perfezionamento nel database core: l'informativa privacy firmata (task senza trigger,
 * da far scaricare e firmare prima di ogni dato personale) e i documenti d'identità con lettura AI
 * sui task PERFEZIONATA di Cessione, Delega, Prestito e Mutuo. Ripetibile; non tocca ciò che è già impostato.
 */
class PerfezionamentoDocumentiSeeder extends Seeder
{
    private const PRODOTTI = ['Cessione', 'Delega', 'Prestito', 'Mutuo'];

    /** nome tipo documento esistente => profilo di lettura AI */
    private const DOCUMENTI_IDENTIFICATIVI = [
        "Carta d'Identità" => 'identita',
        'Codice fiscale / Tessera sanitaria' => 'codice_fiscale',
    ];

    public function run(): void
    {
        $this->informativa();
        $this->documentiIdentificativi();
    }

    private function informativa(): void
    {
        // `document_url` è il link pubblico (o SharePoint) da cui l'agente scarica il modulo: si imposta a mano.
        $type = DocumentType::query()->firstOrCreate(
            ['name' => ProductTaskDocuments::PRIVACY_DOCUMENT],
            [
                'description' => 'Informativa sul trattamento dei dati personali da far firmare al cliente con firma olografa.',
                'is_practice' => true,
                'is_client' => true,
                'is_signed' => true,
                'nature' => 'incoming',
                'ai_kind' => 'informativa',
                'checks' => ['leggibile', 'tipo_documento', 'informativa_firmata'],
            ],
        );

        $task = Task::query()->firstOrCreate(
            ['name' => ProductTaskDocuments::PRIVACY_TASK],
            [
                'description' => 'Informativa privacy da scaricare, far firmare al cliente e caricare prima del perfezionamento.',
                'taskable' => 'pratica',
                'is_active' => true,
            ],
        );

        DocumentRequirement::query()->firstOrCreate(
            ['task_id' => $task->getKey(), 'document_type_id' => $type->getKey()],
            ['is_required' => true, 'sort_order' => 1],
        );
    }

    private function documentiIdentificativi(): void
    {
        $sort = 10;

        foreach (self::DOCUMENTI_IDENTIFICATIVI as $name => $kind) {
            $type = DocumentType::query()->where('name', $name)->first();

            if ($type === null) {
                continue;
            }

            if ($type->ai_kind === null) {
                $type->update(['ai_kind' => $kind]);
            }

            $tasks = Task::query()
                ->where('taskable', 'pratica')
                ->where('trigger_field', 'tipo_prodotto')
                ->whereIn('trigger_value', self::PRODOTTI)
                ->where('trigger_subfield', 'stato_pratica')
                ->where('trigger_subvalue', 'PERFEZIONATA')
                ->get();

            foreach ($tasks as $task) {
                DocumentRequirement::query()->firstOrCreate(
                    ['task_id' => $task->getKey(), 'document_type_id' => $type->getKey()],
                    ['is_required' => true, 'sort_order' => $sort],
                );
            }

            $sort++;
        }
    }
}
