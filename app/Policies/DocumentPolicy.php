<?php

namespace App\Policies;

use App\Filament\Agenti\Resources\Clients\ClientResource;
use App\Filament\Agenti\Resources\Pratiche\PraticaResource;
use App\Models\User as AppUser;
use App\Services\Agenti\AgentImpersonationService;
use Unico\Core\Models\Document;
use Unico\Core\Models\User;

/**
 * Chi può vedere e scaricare un documento. Il personale (company dell'utente, o documento senza company) li vede tutti; un
 * produttore solo quelli legati ai propri dati: le sue pratiche, i suoi clienti e la sua scheda, con gli stessi criteri del portale
 * agenti (le risorse del portale filtrano già per produttore). Senza questa policy bastava la company: un agente poteva scaricare
 * qualsiasi documento della company cambiando l'id nell'indirizzo.
 */
class DocumentPolicy
{
    public function view(User $user, Document $document): bool
    {
        if (! $this->sameCompany($user, $document)) {
            return false;
        }

        return in_array($user->role, AppUser::STAFF_ROLES, true) || $this->belongsToAgent($document);
    }

    public function download(User $user, Document $document): bool
    {
        return $this->view($user, $document);
    }

    private function sameCompany(User $user, Document $document): bool
    {
        return $document->company_id === null || (int) $document->company_id === (int) $user->company_id;
    }

    private function belongsToAgent(Document $document): bool
    {
        $fornitoreId = app(AgentImpersonationService::class)->agentFornitoreId();

        if ($fornitoreId === null) {
            return false;
        }

        $id = $document->documentable_id;

        return match ($document->documentable_type) {
            'fornitore' => (int) $id === $fornitoreId,
            'pratica' => PraticaResource::getEloquentQuery()->whereKey($id)->exists(),
            'client' => ClientResource::getEloquentQuery()->whereKey($id)->exists(),
            default => false,
        };
    }
}
