<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessTaskPrivacyData extends Model
{
    protected $table = 'process_task_privacy_data';

    protected $fillable = [
        'process_task_id',
        'privacy_data_type_id',
        'privacy_legal_base_id',
        'access_level',
        'purpose',
        'retention_period',
        'is_encrypted',
        'is_shared_externally',
        'created_by',
        'updated_by',
    ];

    public $incrementing = false;

    protected $casts = [
        'is_encrypted' => 'boolean',
        'is_shared_externally' => 'boolean',
    ];

    public function privacyDataType(): BelongsTo
    {
        return $this->belongsTo(PrivacyDataType::class, 'privacy_data_type_id');
    }

    public function legalBase(): BelongsTo
    {
        return $this->belongsTo(PrivacyLegalBase::class, 'privacy_legal_base_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
