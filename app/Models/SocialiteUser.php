<?php

namespace App\Models;

use DutchCodingCompany\FilamentSocialite\Models\SocialiteUser as BaseSocialiteUser;

class SocialiteUser extends BaseSocialiteUser
{
    protected $fillable = [
        'user_id',
        'provider',
        'provider_id',
        'email',
        'avatar',
        'is_personal',
    ];

    protected $casts = [
        'is_personal' => 'boolean',
    ];
}
