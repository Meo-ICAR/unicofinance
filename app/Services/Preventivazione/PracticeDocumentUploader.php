<?php

namespace App\Services\Preventivazione;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Unico\Core\Documents\DocumentPipeline;
use Unico\Core\Enums\DocumentStatus;
use Unico\Core\Models\Document;
use Unico\Core\Models\DocumentType;
use Unico\Core\Models\Pratica;

/**
 * Memorizza il file come documento della pratica e lascia a unico-core
 * il riconoscimento del tipo (regex sul nome, poi AI) e l'estrazione dei dati.
 */
class PracticeDocumentUploader
{
    public function __construct(private DocumentPipeline $pipeline) {}

    /**
     * @param  Collection<int, DocumentType>|list<int>  $candidateTypeIds
     */
    public function store(Pratica $pratica, UploadedFile $file, Collection|array $candidateTypeIds, ?User $user, ?int $documentTypeId = null): Document
    {
        $ids = $candidateTypeIds instanceof Collection ? $candidateTypeIds->all() : $candidateTypeIds;

        $document = $documentTypeId === null ? null : Document::query()
            ->where('documentable_type', 'pratica')
            ->where('documentable_id', (string) $pratica->getKey())
            ->where('document_type_id', $documentTypeId)
            ->first();

        if ($document === null) {
            $document = Document::query()->create([
                'company_id' => $pratica->company_id,
                'documentable_type' => 'pratica',
                'documentable_id' => (string) $pratica->getKey(),
                'document_type_id' => $documentTypeId,
                'name' => $file->getClientOriginalName(),
                'status' => DocumentStatus::PENDING,
                'user_id' => $user?->getKey(),
                'uploaded_by' => $user?->getKey(),
            ]);
        } else {
            $document->forceFill([
                'user_id' => $user?->getKey() ?? $document->user_id,
                'uploaded_by' => $user?->getKey() ?? $document->uploaded_by,
            ])->save();
        }

        $document->addMedia($file->getRealPath())
            ->usingFileName($file->getClientOriginalName())
            ->toMediaCollection(Document::COLLECTION);

        $candidates = DocumentType::query()->whereIn('id', $ids)->get();

        $this->pipeline->run($document->fresh(), [
            'classify' => true,
            'candidates' => $candidates,
        ]);

        $document = $document->fresh('documentType');

        if ($document->status === DocumentStatus::PENDING && $document->getFirstMedia(Document::COLLECTION) !== null) {
            $document->update(['status' => DocumentStatus::UPLOADED]);
        }

        return $document->fresh('documentType');
    }
}
