<?php

namespace App\Filament\Resources\Companies\Pages;

use App\Filament\Resources\Companies\CompanyResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;

class CreateCompany extends CreateRecord
{
    protected static string $resource = CompanyResource::class;

    // Variabile temporanea per parcheggiare i dati dell'admin
    public array $adminData = [];

    // 1. Prima di creare l'azienda, "rubiamo" i dati dell'admin dal form
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->adminData = [
            'name' => $data['admin_name'],
            'email' => $data['admin_email'],
            'password' => Hash::make($data['admin_password']),
        ];

        return $data;  // Filament salverà solo il 'name' dell'azienda
    }

    // 2. Subito dopo che l'azienda è stata creata nel DB, creiamo l'utente
    protected function afterCreate(): void
    {
        // $this->record contiene l'azienda appena creata

        // Creiamo il nuovo utente
        $user = User::create($this->adminData);

        // Lo colleghiamo all'azienda assegnandogli il ruolo di 'admin' nella tabella pivot
        $this->record->users()->attach($user->id, [
            'role' => 'admin',
        ]);
    }
}
