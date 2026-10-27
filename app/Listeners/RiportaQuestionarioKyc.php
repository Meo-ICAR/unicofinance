<?php

namespace App\Listeners;

use App\Enums\KycStatus;
use App\Models\Client;
use App\Models\KycQuestionnaire;
use App\Models\Pratica;
use Unico\Core\Documents\PracticeClientPopulator;
use Unico\Core\Events\DocumentAnalyzed;
use Unico\Core\Models\Document;
use Unico\Core\Models\PraticaClient;

/**
 * Letto il questionario di adeguata verifica firmato (profilo `questionario_kyc`), porta nel database anagrafica e domicilio del
 * cliente (PracticeClientPopulator) e le otto risposte nel suo questionario KYC. Completa solo ciò che è vuoto: non sovrascrive mai
 * quello che una persona ha già inserito o corretto.
 */
class RiportaQuestionarioKyc
{
    private const ANSWERS = [
        'pep_status', 'financing_purpose', 'economic_activity', 'activity_sector',
        'activity_location', 'financing_nature', 'income_band', 'wealth_band',
    ];

    public function __construct(private PracticeClientPopulator $populator) {}

    public function handle(DocumentAnalyzed $event): void
    {
        $document = $event->result->document;
        $document->loadMissing('documentType');

        if ($document->documentType?->ai_kind !== 'questionario_kyc') {
            return;
        }

        if ($document->documentable_type === 'client') {
            $client = Client::query()->find($document->documentable_id);

            if ($client !== null && $this->populator->applyToClient($client, $document)) {
                $praticaId = PraticaClient::query()->where('client_id', $client->getKey())->latest('id')->value('pratica_id');
                $this->riporta($client->getKey(), $praticaId === null ? null : (string) $praticaId, $document);
            }

            return;
        }

        if ($document->documentable_type !== 'pratica') {
            return;
        }

        $pratica = Pratica::query()->find($document->documentable_id);

        if ($pratica === null) {
            return;
        }

        $client = $this->populator->apply($pratica, $document);

        if ($client !== null) {
            $this->riporta($client->getKey(), (string) $pratica->getKey(), $document);
        }
    }

    private function riporta(int|string $clientId, ?string $praticaId, Document $document): void
    {
        $fields = $document->analyses()->where('kind', 'extraction')->latest('id')->first()?->result['fields'] ?? null;

        if (! is_array($fields)) {
            return;
        }

        $answers = array_filter(array_intersect_key($fields, array_flip(self::ANSWERS)), fn (mixed $value): bool => filled($value));

        if ($answers === []) {
            return;
        }

        $kyc = KycQuestionnaire::query()->where('client_id', $clientId)->latest('id')->first()
            ?? new KycQuestionnaire(['client_id' => $clientId, 'pratica_id' => $praticaId]);

        $empty = array_filter($answers, fn (mixed $value, string $key): bool => blank($kyc->getRawOriginal($key)), ARRAY_FILTER_USE_BOTH);

        if ($empty === [] && $kyc->exists) {
            return;
        }

        $kyc->fill($empty + ['document_id' => $kyc->document_id ?? $document->getKey()]);

        if (! $kyc->exists) {
            $kyc->status = count($answers) === count(self::ANSWERS) && ($fields['signed'] ?? false) === true ? KycStatus::Complete : KycStatus::Draft;
            $kyc->compiled_at = $kyc->status === KycStatus::Complete ? now() : null;
        }

        $kyc->save();
    }
}
