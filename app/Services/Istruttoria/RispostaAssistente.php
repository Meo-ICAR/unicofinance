<?php

namespace App\Services\Istruttoria;

use App\Enums\ApiProvider;
use App\Models\ChatMessage;
use App\Models\Pratica;
use App\Neuron\IstruttoreAssistant;
use App\Services\ApiCallLogger;
use Illuminate\Support\Str;
use NeuronAI\Chat\History\EloquentChatHistory;
use NeuronAI\Chat\Messages\Usage;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Providers\AIProviderInterface;
use RuntimeException;
use Throwable;

/** Una domanda all'assistente: contesto già pronto, chiamata registrata, nessuna modifica ai dati. */
class RispostaAssistente
{
    public function __construct(
        private ContestoAssistente $contesto,
        private ApiCallLogger $logger,
    ) {}

    public function chiedi(string $domanda, ?Pratica $pratica = null): string
    {
        $domanda = trim($domanda);
        $started = microtime(true);

        try {
            $agent = IstruttoreAssistant::make();
            $agent->contesto = $pratica instanceof Pratica
                ? $this->contesto->pratica($pratica)
                : $this->contesto->coda();

            if (app()->bound(AIProviderInterface::class)) {
                $agent->setAiProvider(app(AIProviderInterface::class));
            }

            $agent->setChatHistory(new EloquentChatHistory($this->threadId($pratica), ChatMessage::class));
            $reply = $agent->chat(new UserMessage($domanda))->getMessage();
            $testo = trim((string) $reply->getContent());
            $usage = $reply->getUsage();

            $this->registra(true, $started, $pratica, $domanda, $usage instanceof Usage ? $usage : null, null);

            return $testo !== '' ? $testo : 'Nessuna risposta.';
        } catch (Throwable $e) {
            $messaggio = Str::limit($this->contesto->pulisci($e->getMessage()), 500, '');
            $this->registra(false, $started, $pratica, $domanda, null, $messaggio);

            throw new RuntimeException('Non riesco a rispondere in questo momento: '.$messaggio, previous: $e);
        }
    }

    /**
     * @return list<array{ruolo: string, testo: string}>
     */
    public function messaggi(?Pratica $pratica = null): array
    {
        if (auth()->id() === null) {
            return [];
        }

        return ChatMessage::query()
            ->where('thread_id', $this->threadId($pratica))
            ->orderBy('id')
            ->get(['role', 'content'])
            ->map(function (ChatMessage $messaggio): ?array {
                $testo = $this->testo($messaggio->content);

                if ($testo === '' || ! in_array($messaggio->role, ['user', 'assistant'], true)) {
                    return null;
                }

                return ['ruolo' => $messaggio->role, 'testo' => $testo];
            })
            ->filter()
            ->values()
            ->all();
    }

    public function threadId(?Pratica $pratica): string
    {
        $base = 'istruttore:'.auth()->id();

        return $pratica instanceof Pratica ? $base.':pratica:'.$pratica->getKey() : $base;
    }

    private function registra(bool $ok, float $started, ?Pratica $pratica, string $domanda, ?Usage $usage, ?string $errore): void
    {
        $this->logger->record(
            provider: ApiProvider::Anthropic,
            operation: 'istruttore.assistente',
            subject: $pratica,
            request: [
                'modello' => (string) config('services.anthropic.model'),
                'domanda' => Str::limit($this->contesto->pulisci($domanda), 500, ''),
            ],
            startedAt: $started,
            httpStatus: $ok ? 200 : null,
            successful: $ok,
            summary: 'Assistente istruttoria',
            error: $errore,
            response: $usage === null ? null : [
                'input_tokens' => $usage->inputTokens,
                'output_tokens' => $usage->outputTokens,
            ],
        );
    }

    private function testo(mixed $content): string
    {
        if (is_string($content)) {
            return trim($content);
        }

        if (! is_array($content)) {
            return '';
        }

        $parti = [];

        foreach ($content as $blocco) {
            if (is_array($blocco) && ($blocco['type'] ?? null) === 'text') {
                $parti[] = (string) ($blocco['content'] ?? '');
            }
        }

        return trim(implode("\n", $parti));
    }
}
