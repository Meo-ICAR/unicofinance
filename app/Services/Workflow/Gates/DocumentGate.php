<?php

namespace App\Services\Workflow\Gates;

use App\Models\Document;

class DocumentGate
{
    public function canView(Document $document): bool
    {
        // Nel BPM: App\Services\Workflow\Gates\DocumentGate

        $response = Http::withToken(config('services.unicodoc.token'))
            ->post(config('services.unicodoc.url') . '/api/v1/compliance/check', [
                'documentable_type' => 'App\Models\Client',
                'documentable_id' => $client->id,
                'required_codes' => $step->required_document_codes
            ]);

        if ($response->json('is_compliant')) {
            // 🟢 Tutti i documenti sono OK.
            $process->moveToNextStep();
        } else {
            // 🔴 Blocca il processo e notifica l'agente
            $missing = implode(', ', $response->json('details.missing_documents'));

            $process->suspend("Mancano i seguenti documenti: {$missing}");

            // Potresti inviare una mail automatica al cliente con il link
            // per caricare i documenti mancanti su UnicoDoc.
        }
        // TODO: Implement document view authorization
        return true;
    }
}
