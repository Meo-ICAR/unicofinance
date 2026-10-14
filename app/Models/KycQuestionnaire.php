<?php

namespace App\Models;

use App\Enums\KycStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Questionario di adeguata verifica (KYC) del richiedente, come in unicoloan. */
class KycQuestionnaire extends Model
{
    protected $table = 'kyc_questionnaires';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'pep_status' => \App\Enums\KycPepStatus::class,
            'risk_level' => \App\Enums\KycRiskLevel::class,
            'status' => KycStatus::class,
            'compiled_at' => 'datetime',
            'verified_at' => 'datetime',
            'economic_activity' => \App\Enums\KycEconomicActivity::class,
            'activity_sector' => \App\Enums\KycActivitySector::class,
            'activity_location' => \App\Enums\KycActivityLocation::class,
            'financing_nature' => \App\Enums\KycFinancingNature::class,
            'income_band' => \App\Enums\KycIncomeBand::class,
            'wealth_band' => \App\Enums\KycWealthBand::class,
            'acts_for_third_party' => 'boolean',
            'legal_nature' => \App\Enums\KycLegalNature::class,
            'geographic_area' => \App\Enums\KycGeographicArea::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $questionnaire): void {
            if (blank($questionnaire->status)) {
                $questionnaire->status = KycStatus::Draft;
            }
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }
}
