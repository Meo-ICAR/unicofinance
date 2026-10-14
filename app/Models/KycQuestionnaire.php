<?php

namespace App\Models;

use App\Enums\KycActivityLocation;
use App\Enums\KycActivitySector;
use App\Enums\KycEconomicActivity;
use App\Enums\KycFinancingNature;
use App\Enums\KycGeographicArea;
use App\Enums\KycIncomeBand;
use App\Enums\KycLegalNature;
use App\Enums\KycPepStatus;
use App\Enums\KycRiskLevel;
use App\Enums\KycStatus;
use App\Enums\KycWealthBand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Questionario di adeguata verifica (KYC) del richiedente, come in unicoloan. */
class KycQuestionnaire extends Model
{
    protected $table = 'kyc_questionnaires';

    protected $guarded = ['id'];

    /** Tabella dell'app: senza, una relazione dal cliente (database core) erediterebbe la connessione core. */
    public function getConnectionName(): ?string
    {
        return config('database.default');
    }

    protected function casts(): array
    {
        return [
            'pep_status' => KycPepStatus::class,
            'risk_level' => KycRiskLevel::class,
            'status' => KycStatus::class,
            'compiled_at' => 'datetime',
            'verified_at' => 'datetime',
            'economic_activity' => KycEconomicActivity::class,
            'activity_sector' => KycActivitySector::class,
            'activity_location' => KycActivityLocation::class,
            'financing_nature' => KycFinancingNature::class,
            'income_band' => KycIncomeBand::class,
            'wealth_band' => KycWealthBand::class,
            'acts_for_third_party' => 'boolean',
            'legal_nature' => KycLegalNature::class,
            'geographic_area' => KycGeographicArea::class,
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
