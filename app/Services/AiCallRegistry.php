<?php

namespace App\Services;

use App\Enums\ApiProvider;
use App\Filament\Resources\ApiCalls\ApiCallResource;
use Unico\Core\Documents\Contracts\AiCallLog;
use Unico\Core\Models\Document;

/** Le chiamate AI andate male sui documenti finiscono nel registro delle chiamate ai web service, da cui si capisce la causa. */
class AiCallRegistry implements AiCallLog
{
    public function __construct(private ApiCallLogger $logger) {}

    public function failed(Document $document, string $operation, string $error): ?string
    {
        $chiamata = $this->logger->record(
            ApiProvider::Anthropic,
            $operation,
            $document,
            ['documento' => $document->getKey(), 'tipo' => $document->documentType?->name],
            microtime(true),
            null,
            false,
            summary: 'Chiamata AI non riuscita',
            error: $error,
        );

        return (string) $chiamata->getKey();
    }

    public function url(string $reference): ?string
    {
        return ApiCallResource::getUrl('view', ['record' => $reference], panel: 'admin');
    }
}
