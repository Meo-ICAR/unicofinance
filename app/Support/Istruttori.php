<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Unico\Core\Models\Employee;

/** Chi è istruttore: il dipendente con il ruolo «istruttore» in `employee_roles`, come nell'organigramma di unicooam (Dipendenti). */
class Istruttori
{
    public const RUOLO = 'istruttore';

    /**
     * @param  Builder<Employee>  $query
     * @return Builder<Employee>
     */
    public static function scope(Builder $query): Builder
    {
        return $query->whereJsonContains('employee_roles', self::RUOLO);
    }

    public static function e(Employee $dipendente): bool
    {
        return in_array(self::RUOLO, $dipendente->employee_roles ?? [], true);
    }
}
