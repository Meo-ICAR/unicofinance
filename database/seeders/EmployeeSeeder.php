<?php

namespace Database\Seeders;

use App\Enums\EmployeeType;
use App\Enums\SupervisorType;
use App\Models\Company;
use App\Models\CompanyBranch;
use App\Models\Employee;
use Illuminate\Database\Seeder;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();

        if (!$company) {
            return;
        }

        $branchId = CompanyBranch::where('company_id', $company->id)->value('id');

        $employees = [
            ['name' => 'DOGLIONI DONATELLA',  'email' => 'donatella.doglioni@races.it', 'phone' => '340 9395636',  'oam_at' => null,         'oam_name' => null,            'numero_iscrizione_rui' => null],
            ['name' => 'SAJEVA PAOLO',         'email' => 'paolo.sajeva@races.it',       'phone' => '348 3860075', 'oam_at' => null,         'oam_name' => null,            'numero_iscrizione_rui' => null],
            ['name' => 'VALENTE LUCA',         'email' => 'luca.valente@races.it',       'phone' => '340 688 7573','oam_at' => '2019-05-28', 'oam_name' => 'VALENTE LUCA',  'numero_iscrizione_rui' => 'E000629852'],
            ['name' => 'MAGLIANO ANGELA',      'email' => 'angela.magliano@races.it',    'phone' => '349 0507635', 'oam_at' => null,         'oam_name' => null,            'numero_iscrizione_rui' => null],
            ['name' => 'BASTIONE DAVIDE',      'email' => 'davide.bastione@races.it',    'phone' => '06 86768121', 'oam_at' => null,         'oam_name' => null,            'numero_iscrizione_rui' => null],
            ['name' => 'LONGO MICHELE',        'email' => 'michele.longo@races.it',      'phone' => '3283025796',  'oam_at' => '2007-02-01', 'oam_name' => 'LONGO MICHELE', 'numero_iscrizione_rui' => 'E000039926'],
            ['name' => 'MALPELI GERTRUDE',     'email' => 'gertrude.malpeli@races.it',   'phone' => '06 99317742', 'oam_at' => '2023-03-21', 'oam_name' => 'MALPELI GERTRUDE','numero_iscrizione_rui' => 'E000726797'],
            ['name' => 'ZACCARIA LOREDANA',    'email' => 'loredana.zaccaria@races.it',  'phone' => '339 6781274', 'oam_at' => null,         'oam_name' => null,            'numero_iscrizione_rui' => null],
        ];

        foreach ($employees as $data) {
            Employee::updateOrCreate(
                ['email' => $data['email'], 'company_id' => $company->id],
                [
                    'name'                  => $data['name'],
                    'phone'                 => $data['phone'],
                    'oam_at'                => $data['oam_at'],
                    'oam_name'              => $data['oam_name'],
                    'numero_iscrizione_rui' => $data['numero_iscrizione_rui'],
                    'employee_types'        => EmployeeType::DIPENDENTE,
                    'supervisor_type'       => SupervisorType::NO,
                    'company_branch_id'     => $branchId,
                    'hiring_date'           => '2026-03-19',
                    'is_structure'          => false,
                    'is_ghost'              => false,
                ]
            );
        }
    }
}
