<?php

namespace App\Models;

use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Company extends Model implements HasName, HasAvatar, HasMedia
{
    use HasFactory, HasUuids, InteractsWithMedia;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'name',
        'domain',
        'vat_number',
        'vat_name',
        'oam',
        'oam_at',
        'oam_name',
        'numero_iscrizione_rui',
        'ivass',
        'ivass_at',
        'ivass_name',
        'ivass_section',
        'sponsor',
        'company_type',
        'page_header',
        'page_footer',
        'smtp_host',
        'smtp_port',
        'smtp_username',
        'smtp_password',
        'smtp_encryption',
        'smtp_from_email',
        'smtp_from_name',
        'smtp_enabled',
        'smtp_verify_ssl',
    ];

    protected $casts = [
        'oam_at' => 'date',
        'ivass_at' => 'date',
        'smtp_enabled' => 'boolean',
        'smtp_verify_ssl' => 'boolean',
        'smtp_password' => 'encrypted',
    ];

    protected $hidden = [
        'smtp_password',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function branches(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CompanyBranch::class);
    }

    /**
     * Verifica se un'email appartiene al dominio aziendale.
     */
    public function ownsEmail(string $email): bool
    {
        if (empty($this->domain)) {
            return false;
        }

        $emailDomain = explode('@', $email);
        $emailDomain = end($emailDomain);
        
        return strtolower($emailDomain) === strtolower($this->domain);
    }

    /**
     * Implementazione di HasName per Filament (Nome Tenant)
     */
    public function getFilamentName(): string
    {
        return $this->name;
    }

    /**
     * Implementazione di HasAvatarUrl per Filament (Logo Tenant)
     */
    public function getFilamentAvatarUrl(): ?string
    {
        // Utile per restituire un logo da Spatie Media Library come avatar del tenant in Filament
        return $this->getFirstMediaUrl('logo') ?: null;
    }
}
