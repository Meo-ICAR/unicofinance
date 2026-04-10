<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Wildside\Userstamps\Traits\Userstamps;

class ProcessRequestMapping extends Model
{
    use SoftDeletes, Userstamps;

    protected $guarded = ['id'];

    protected $fillable = [
        'request_type',
        'process_id',
        'is_suggested',
    ];

    protected $casts = [
        'is_suggested' => 'boolean',
    ];

    public function process()
    {
        return $this->belongsTo(Process::class);
    }

    /**
     * Scope per ottenere solo i mapping suggeriti
     */
    public function scopeSuggested($query)
    {
        return $query->where('is_suggested', true);
    }

    /**
     * Scope per tipo di richiesta
     */
    public function scopeByRequestType($query, string $requestType)
    {
        return $query->where('request_type', $requestType);
    }
}
