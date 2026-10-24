<?php

namespace App\Neuron;

use App\Services\Istruttoria\ContestoAssistente;
use NeuronAI\Agent\Agent;
use NeuronAI\Agent\SystemPrompt;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Anthropic\Anthropic;
use RuntimeException;

/**
 * Assistente in sola lettura per l'istruttore. Il contesto arriva da PHP:
 * niente SQL, niente file dei documenti, niente azioni che modificano i dati.
 */
class IstruttoreAssistant extends Agent
{
    public string $contesto = '';

    protected function provider(): AIProviderInterface
    {
        $key = config('services.anthropic.key');

        if (blank($key)) {
            throw new RuntimeException('Chiave API Anthropic mancante: imposta ANTHROPIC_API_KEY nel file .env');
        }

        return new Anthropic(
            key: (string) $key,
            model: (string) config('services.anthropic.model', 'claude-sonnet-4-6'),
            max_tokens: 4096,
        );
    }

    protected function instructions(): string
    {
        $manuale = app(ContestoAssistente::class)->manuale();

        return (string) new SystemPrompt(
            background: [
                'Sei l\'assistente dell\'istruttore di UnicoFinance, applicazione per il mediatore creditizio.',
                'Rispondi solo con il contesto operativo qui sotto e, se presenti, con il manuale utente e il manuale tecnico. Non interrogare il database e non inventare procedure.',
                'Sei in sola lettura. Non cambi stato, non invii email, non carichi documenti e non scrivi sul portale della banca.',
                $this->contesto !== '' ? "Contesto operativo:\n".$this->contesto : 'Nessun contesto operativo è stato preparato.',
                $manuale !== '' ? "Manuali (utente e tecnico):\n".$manuale : 'I manuali non sono disponibili: rispondi solo sul contesto operativo.',
            ],
            steps: [
                'Se il contesto o il manuale non contengono la risposta, dillo invece di inventare una procedura.',
                'Se l\'istruttore chiede di cambiare stato, inviare un sollecito, prendere visione, caricare un documento o scrivere sul portale della banca, indica l\'azione già presente in interfaccia (Cambia stato, Invia sollecito, Presa visione cambio stato) e non dire che l\'hai eseguita.',
                'Il benestare del datore di lavoro e la trascrizione nel portale della banca non partono dall\'app.',
                'Non riportare codici fiscali, IBAN, token, credenziali o il contenuto dei file.',
            ],
            output: [
                'Rispondi in italiano, in modo diretto e operativo.',
            ],
        );
    }

    protected function tools(): array
    {
        return [];
    }
}
