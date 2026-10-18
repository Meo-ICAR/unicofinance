<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Client autorizzato a consegnare pratiche e documenti sull'API /api/agente/v1. */
class AgentApiClient extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'last_used_at' => 'datetime',
        ];
    }
}
