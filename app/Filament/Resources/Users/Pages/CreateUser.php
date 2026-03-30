<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    public ?string $tenantRole = null;

    // 1. Intercettiamo i dati prima della creazione per estrarre il ruolo
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->tenantRole = $data['tenant_role'] ?? 'user';

        return $data;
    }

    // 2. Dopo che Filament ha creato l'utente e lo ha associato all'azienda, aggiorniamo la pivot
    protected function afterCreate(): void
    {
        $tenant = Filament::getTenant();

        if ($tenant && $this->tenantRole) {
            $this->record->companies()->updateExistingPivot($tenant->id, [
                'role' => $this->tenantRole,
            ]);
        }
    }
}
