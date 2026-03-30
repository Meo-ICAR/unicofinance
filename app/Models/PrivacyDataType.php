<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PrivacyDataType extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'category',
        'retention_years',
    ];

    protected $casts = [
        'category' => 'string',
        'retention_years' => 'integer',
    ];

    /**
     * I task di processo che utilizzano questo tipo di dato.
     */
    public function processTasks(): BelongsToMany
    {
        return $this
            ->belongsToMany(ProcessTask::class, 'process_task_privacy_data')
            ->withPivot('access_level')
            ->withTimestamps()
            ->withUserstamps();
    }

    /**
     * Scope per filtrare per categoria.
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }
}
