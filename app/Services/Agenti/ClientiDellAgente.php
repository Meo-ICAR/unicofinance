<?php

namespace App\Services\Agenti;

use App\Models\Client;
use App\Models\ClientRelation;
use App\Models\Pratica;
use Illuminate\Support\Collection;
use Unico\Core\Models\PraticaClient;

/** I clienti delle pratiche del produttore e i datori di lavoro di quei clienti: il produttore non vede altro. */
class ClientiDellAgente
{
    public function __construct(private AgentImpersonationService $agenti) {}

    /** @return Collection<int, int> */
    public function ids(): Collection
    {
        $fornitoreId = $this->agenti->agentFornitoreId();

        if ($fornitoreId === null) {
            return collect();
        }

        $pratiche = Pratica::query()->where('fornitori_id', $fornitoreId);
        $codici = (clone $pratiche)->whereNotNull('codice_fiscale')->pluck('codice_fiscale');

        return Client::query()
            ->where(fn ($query) => $query->whereIn('tax_code', $codici)->orWhereIn('vat_number', $codici)
                ->orWhereIn('id', PraticaClient::query()->whereIn('pratica_id', (clone $pratiche)->select('id'))->select('client_id')))
            ->pluck('id');
    }

    /** I datori di lavoro dei clienti del produttore (relazione `dipendente`). @return Collection<int, int> */
    public function datoriIds(): Collection
    {
        return ClientRelation::query()
            ->where('role', ClientRelation::RUOLO_DIPENDENTE)
            ->whereIn('client_id', $this->ids())
            ->distinct()
            ->pluck('company_id');
    }
}
