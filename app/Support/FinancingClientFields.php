<?php

namespace App\Support;

use App\Enums\SimulationContractType;

/** Campi del cliente pertinenti al tipo di finanziamento, come in unicoloan. */
class FinancingClientFields
{
    /**
     * @return list<string>
     */
    public static function forContract(SimulationContractType $contract): array
    {
        $fields = [
            'first_name',
            'name',
            'tax_code',
            'birth_date',
            'birth_place',
            'sex',
            'citizenship',
            'hiring_date',
            'salary',
        ];

        if ($contract->asksEmployer()) {
            $fields[] = 'employer_id';
        }

        if ($contract->asksQuotaCedibile()) {
            $fields[] = 'salary_quote';
        }

        return $fields;
    }
}
