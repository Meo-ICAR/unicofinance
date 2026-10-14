<?php

namespace App\Services;

use App\Enums\ApiCallStatus;
use App\Enums\ApiProvider;
use App\Models\ApiCall;
use Illuminate\Database\Eloquent\Model;

/**
 * Registra le chiamate ai web service esterni; il costo si addebita solo alle chiamate riuscite.
 */
class ApiCallLogger
{
    /**
     * @param  array<string, mixed>  $request  parametri inviati, mai le credenziali
     * @param  array<string, mixed>|null  $response
     */
    public function record(
        ApiProvider $provider,
        string $operation,
        ?Model $subject,
        array $request,
        float $startedAt,
        ?int $httpStatus,
        bool $successful,
        ?string $summary = null,
        ?string $reference = null,
        ?string $error = null,
        ?float $cost = null,
        ?array $response = null,
    ): ApiCall {
        return ApiCall::create([
            'provider' => $provider,
            'operation' => $operation,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'user_id' => auth()->id(),
            'status' => $successful ? ApiCallStatus::Success : ApiCallStatus::Failed,
            'http_status' => $httpStatus,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'cost' => $successful ? ($cost ?? $provider->unitCost()) : 0,
            'request' => $request,
            'response' => $response,
            'summary' => $summary,
            'reference' => $reference,
            'error' => $error,
        ]);
    }
}
