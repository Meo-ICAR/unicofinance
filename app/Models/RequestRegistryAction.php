<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestRegistryAction extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'action_date' => 'datetime',
    ];

    public function registry(): BelongsTo
    {
        return $this->belongsTo(RequestRegistry::class, 'registry_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
