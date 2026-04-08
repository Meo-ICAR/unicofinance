<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrivacyLegalBase extends Model
{
    protected $fillable = [
        'name',
        'reference_article',
        'description',
    ];
}
