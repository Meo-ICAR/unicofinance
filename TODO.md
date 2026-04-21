leggi ARCHITECTURE.md . voglio che in questo BPM possa fare la creazione di un nuovo  TaskExecution e TaskExecutionChecklistItem , passando target_id,  employee_id, client_id process_id richiama Process ( da cui eredita target_type) e  sia possibile da un altra app sempre in laravel 13 su altro database dello stesso server


i TaskExecutionChecklistItem avanzano chiamando delle cheklistItem->require_condition_class , skip_condition_class, action_class. che risiedono sotto app/Contracts e app/Actions. anche qui voglio richiamarli con api

AdvanceChecklistItemAction — domain action che valuta skip/require, esegue action_class, e aggiorna lo stato
BpmChecklistItemController — REST controller (toggle + evaluate + uncheck)

Ecco un esempio completo di una classe Controller (o Service) all'interno della tua seconda applicazione.

Questo esempio simula un flusso reale:

Viene creato un nuovo task BPM su UnicoFinance.
Viene recuperato l'ID dell'esecuzione appena creata.
Viene letta la lista degli elementi (checklist) di quel task.
Viene fatto avanzare dinamicamente il primo step della checklist.
php
<?php
namespace App\Http\Controllers;
use App\Services\UnicoFinanceBpmClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
class PraticaBpmController extends Controller
{
    public function __construct(
        protected UnicoFinanceBpmClient $bpmClient
    ) {}
    /**
     * Esempio completo: Crea un processo BPM e ne fa avanzare un task (checklist item)
     */
    public function avviaECompletaProcesso(Request $request): JsonResponse
    {
        try {
            // ── 1. CREAZIONE DEL PROCESSO BPM SU UNICOFINANCE ────────────

            // Ipotizziamo che tu voglia lanciare il Processo ID 3 (es. Onboarding)
            // per un determinato cliente e un documento (target_id)
            $responseCreazione = $this->bpmClient->createExecution(
                processId: 3,
                targetId: $request->input('document_id', 1042), // L'ID del modello target
                employeeId: null, // Opzionale
                clientId: $request->input('client_id', 25),     // Opzionale
                idempotencyKey: 'onboarding-doc-' . $request->input('document_id', 1042)
            );
            // Recuperiamo l'ID della prima esecuzione creata (un Processo può avere più task)
            $executionId = $responseCreazione['executions'][0]['id'];

            Log::info("Processo BPM avviato con successo. Execution ID: {$executionId}");
            // ── 2. LETTURA DEGLI STEP (CHECKLIST) DEL TASK ───────────────
            // Ora che abbiamo il task, vogliamo vedere quali sono gli step da completare
            $checklistResponse = $this->bpmClient->getChecklistItems($executionId);
            $items = $checklistResponse['items'];
            if (empty($items)) {
                return response()->json([
                    'message' => 'Processo creato, ma nessuna checklist presente.',
                    'execution_id' => $executionId
                ]);
            }
            // Prendiamo l'ID del primo elemento della checklist per questo esempio
            // Nella realtà potresti cercare un item specifico in base alla 'instruction' o all'ID
            $firstChecklistItemId = $items[0]['id'];
            // ── 3. AVANZAMENTO DELLO STEP (CHECKLIST ITEM) ───────────────
            // Facciamo avanzare questo step.
            // Se UnicoFinance ha una "skip_condition_class" per questo item, verrà valutata.
            // Se ha una "action_class", verrà eseguita in background su UnicoFinance passando questi $params.
            $paramsPerActionClass = [
                'nota' => 'Documento verificato automaticamente dal sistema esterno',
                'esito_automatico' => true
            ];
            $checkResponse = $this->bpmClient->checkChecklistItem(
                $executionId,
                $firstChecklistItemId,
                $paramsPerActionClass
            );
            // Controlliamo l'esito dell'avanzamento
            if ($checkResponse['skipped']) {
                $statoStep = "Step saltato automaticamente (condizione di skip verificata).";
            } elseif ($checkResponse['action_executed']) {
                $statoStep = "Step marcato come completato e action_class eseguita su UnicoFinance.";
            } else {
                $statoStep = "Step marcato come completato (nessuna action_class da eseguire).";
            }
            // ── 4. RITORNO AL CLIENT (FRONTEND DELL'ALTRA APP) ───────────

            return response()->json([
                'success' => true,
                'message' => 'Processo BPM creato e primo step avanzato.',
                'execution_id' => $executionId,
                'dettagli_creazione' => $responseCreazione,
                'esito_step' => $statoStep,
                'risposta_check' => $checkResponse,
            ]);
        } catch (\RuntimeException $e) {
            // L'API di UnicoFinance ha restituito un errore (es. validazione fallita,
            // processo non esistente, idempotency conflict)
            Log::error('Errore durante integrazione BPM: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Integrazione fallita',
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
Che cosa succede "dietro le quinte" con questo script:
La tua seconda app fa una POST a http://unicofinance/api/bpm/executions.
UnicoFinance salva i record nella sua tabella task_executions e task_execution_checklist_items e ti restituisce un execution_id (es. 150).
La tua app fa una GET a http://unicofinance/api/bpm/executions/150/checklist e riceve tutti gli step da compiere.
La tua app fa un'altra POST a http://unicofinance/api/bpm/executions/150/checklist/800/check.
UnicoFinance riceve la POST, cerca il ChecklistItem 800, esegue la sua action_class (usando le classi del proprio framework), aggiorna il database e ti restituisce l'ok.
