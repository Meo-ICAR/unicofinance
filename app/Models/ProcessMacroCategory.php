<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
// use Wildside\Userstamps\Traits\HasUserstamps;

class ProcessMacroCategory extends Model
{
    use SoftDeletes;

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
