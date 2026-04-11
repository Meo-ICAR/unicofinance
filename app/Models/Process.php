<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Process extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'business_function_id',
        'owner_function_id',
        'process_macro_category_id',
        'name',
        'description',
        'target_model',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function businessFunction(): BelongsTo
    {
        return $this->belongsTo(BusinessFunction::class);
    }

    public function ownerFunction(): BelongsTo
    {
        return $this->belongsTo(BusinessFunction::class, 'owner_function_id');
    }

    public function processMacroCategory(): BelongsTo
    {
        return $this->belongsTo(ProcessMacroCategory::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ProcessTask::class)->orderBy('sequence_number');
    }

    public function requestMappings(): HasMany
    {
        return $this->hasMany(ProcessRequestMapping::class);
    }

    /**
     * Scope per filtrare processi che trattano dati particolari.
     */
    public function scopeWithSpecialData($query)
    {
        return $query->whereHas('tasks.privacyDataTypes', function ($q) {
            $q->where('category', 'particolari');
        });
    }

    /**
     * Scope per filtrare processi che trattano dati giudiziari.
     */
    public function scopeWithJudicialData($query)
    {
        return $query->whereHas('tasks.privacyDataTypes', function ($q) {
            $q->where('category', 'giudiziari');
        });
    }

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Scope per filtrare processi attivi con request mappings per tipo.
     */
    public function scopeActiveWithRequestType($query, string $requestType)
    {
        return $query->where('is_active', true)
            ->whereHas('requestMappings', function ($q) use ($requestType) {
                $q->where('request_type', $requestType);
            })
            ->withExists([
                'requestMappings as is_suggested_exists' => function ($q) use ($requestType) {
                    $q->where('request_type', $requestType)->where('is_suggested', true);
                }
            ]);
    }

    /**
     * Ottiene i processi attivi per tipo di richiesta ordinati per suggerimento.
     * Restituisce un array ['process_id' => 'process_name'].
     */
    public static function getActiveForRequestType(string $requestType): array
    {
        return self::query()
            ->where('is_active', true)
            ->whereHas('requestMappings', function ($q) use ($requestType) {
                $q->where('request_type', $requestType);
            })
            ->join('process_request_mappings', 'processes.id', '=', 'process_request_mappings.process_id')
            ->where('process_request_mappings.request_type', $requestType)
            ->select('processes.id', 'processes.name', 'process_request_mappings.is_suggested')
            ->orderBy('process_request_mappings.is_suggested', 'desc')
            ->orderBy('processes.name')
            ->pluck('processes.name', 'processes.id')
            ->toArray();
    }
}
