<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcessMacroCategory extends Model
{
    protected $guarded = ['id'];

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function processes()
    {
        return $this->hasMany(Process::class);
    }
}
