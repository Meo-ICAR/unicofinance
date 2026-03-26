<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyBranch extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'is_main_office',
        'manager_first_name',
        'manager_last_name',
        'manager_tax_code',
    ];

    protected $casts = [
        'is_main_office' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
