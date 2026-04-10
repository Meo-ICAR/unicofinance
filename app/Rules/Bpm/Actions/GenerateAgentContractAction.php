<?php

declare(strict_types=1);

namespace App\Rules\Bpm\Actions;

use App\Contracts\BpmAction;
use App\Contracts\BpmActionInterface;
use App\Models\Agent;
use App\Models\TaskExecution;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * GenerateAgentContractAction
 *
 * Generates a pre-filled PDF contract using the Agent's personal data
 * (first name, last name, fiscal code) and stores it under
 * `storage/app/private/agents/contracts/`.
 *
 * PDF Generation Strategy:
 *   1. Renders a Blade view (`emails.contracts.agent-contract`) with the
 *      agent's data injected.
 *   2. Converts the HTML to PDF using `wkhtmltopdf` (via Symfony Process)
 *      if the binary is available, otherwise falls back to a simple
 *      HTML-to-PDF approach using the `barryvdh/laravel-dompdf` package.
 *   3. Updates the Agent's `contract_path` with the relative storage path.
 *
 * Prerequisites:
 *   - Install barryvdh/laravel-dompdf: `composer require barryvdh/laravel-dompdf`
 *   - Publish the config: `php artisan vendor:publish --provider="Barryvdh\DomPDF\ServiceProvider"`
 *
 * If neither wkhtmltopdf nor dompdf are available, the action throws
 * a RuntimeException with setup instructions.
 *
 * Registered in checklist_items.action_class as:
 *   App\Rules\Bpm\Actions\GenerateAgentContractAction
 */
final class GenerateAgentContractAction implements BpmAction, BpmActionInterface
{
    use ResolvesTargetAgent;

    /**
     * Storage disk used for contract files (private = not web-accessible).
     */
    private const DISK = 'private';

    /**
     * Relative directory within the disk.
     */
    private const DIRECTORY = 'agents/contracts';

    public function execute(TaskExecution $execution, array $params = []): void
    {
        $agent = $this->resolveAgent($execution);

        // Skip regeneration if a contract already exists
        if ($agent->hasContract()) {
            Log::info('GenerateAgentContractAction: contract already exists, skipping', [
                'agent_id' => $agent->id,
                'contract_path' => $agent->contract_path,
            ]);

            return;
        }

        $fileName = $this->generateFileName($agent);
        $pdfContent = $this->renderPdf($agent);

        $fullPath = self::DIRECTORY . '/' . $fileName;

        Storage::disk(self::DISK)->put($fullPath, $pdfContent, 'private');

        $agent->update(['contract_path' => $fullPath]);

        Log::info('GenerateAgentContractAction: contract generated', [
            'agent_id' => $agent->id,
            'contract_path' => $fullPath,
        ]);
    }

    /**
     * Render the PDF content for the given agent.
     *
     * Tries Barryvdh\DomPDF first, falls back to wkhtmltopdf binary.
     */
    private function renderPdf(Agent $agent): string
    {
        // Attempt 1: barryvdh/laravel-dompdf
        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return $this->renderWithDompdf($agent);
        }

        // Attempt 2: wkhtmltopdf binary
        if ($this->wkhtmltopdfAvailable()) {
            return $this->renderWithWkhtmltopdf($agent);
        }

        throw new \RuntimeException(
            'Impossibile generare il contratto PDF. '
            . 'Installa "barryvdh/laravel-dompdf" (composer require barryvdh/laravel-dompdf) '
            . 'oppure wkhtmltopdf sul server.'
        );
    }

    /**
     * Render using Barryvdh DomPDF package.
     */
    private function renderWithDompdf(Agent $agent): string
    {
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView(
            'contracts.agent-contract',  // Blade view
            ['agent' => $agent]
        );

        return $pdf->output();
    }

    /**
     * Render using wkhtmltopdf CLI binary as fallback.
     */
    private function renderWithWkhtmltopdf(Agent $agent): string
    {
        $html = view('contracts.agent-contract', ['agent' => $agent])->render();
        $tmpHtml = tmpfile();
        fwrite($tmpHtml, $html);
        $meta = stream_get_meta_data($tmpHtml);

        $process = new Process(['wkhtmltopdf', '--quiet', $meta['uri'], '-']);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $process->getOutput();
    }

    /**
     * Check if wkhtmltopdf binary is available on the system.
     */
    private function wkhtmltopdfAvailable(): bool
    {
        $process = new Process(['which', 'wkhtmltopdf']);
        $process->run();

        return $process->isSuccessful();
    }

    /**
     * Generate a unique, deterministic file name for the contract.
     */
    private function generateFileName(Agent $agent): string
    {
        $slug = Str::slug($agent->full_name . '-' . $agent->fiscal_code);
        $timestamp = now()->format('YmdHis');

        return "{$slug}-contract-{$timestamp}.pdf";
    }
}
