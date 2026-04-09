<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SuppressionList extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'request_date' => 'date',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
