<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeadTransfer extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function lead()
    {
        return $this->belongsTo(Client::class, 'lead_id');
    }

    public function purchaser()
    {
        return $this->belongsTo(Client::class, 'purchaser_id');
    }
}
