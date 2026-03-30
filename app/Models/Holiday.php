<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'holiday_date',
        'is_recurring',
    ];

    protected $casts = [
        'holiday_date' => 'date',
        'is_recurring' => 'boolean',
    ];

    /**
     * Scope per festività in un dato anno.
     */
    public function scopeByYear($query, int $year)
    {
        return $query->whereYear('holiday_date', $year);
    }

    /**
     * Scope per festività ricorrenti.
     */
    public function scopeRecurring($query)
    {
        return $query->where('is_recurring', true);
    }

    /**
     * Scope per festività non ricorrenti.
     */
    public function scopeNonRecurring($query)
    {
        return $query->where('is_recurring', false);
    }

    /**
     * Scope per festività tra due date.
     */
    public function scopeBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('holiday_date', [$startDate, $endDate]);
    }

    /**
     * Verifica se una data è festività.
     */
    public static function isHoliday(\DateTime $date): bool
    {
        return static::where('holiday_date', $date->format('Y-m-d'))
            ->orWhere(function ($query) use ($date) {
                $query->where('is_recurring', true)
                    ->whereRaw("DATE_FORMAT(holiday_date, '%m-%d') = ?", [$date->format('m-d')]);
            })
            ->exists();
    }

    /**
     * Ottieni tutte le festività per un dato anno.
     */
    public static function getHolidaysByYear(int $year): array
    {
        $holidays = static::byYear($year)->pluck('holiday_date')->toArray();

        // Aggiungi festività ricorrenti da altri anni
        $recurring = static::recurring()
            ->whereYear('holiday_date', '!=', $year)
            ->get()
            ->map(function ($holiday) use ($year) {
                $date = \DateTime::createFromFormat('Y-m-d', $holiday->holiday_date);
                $date->setDate($year, (int) $date->format('m'), (int) $date->format('d'));

                return $date->format('Y-m-d');
            })
            ->toArray();

        return array_unique(array_merge($holidays, $recurring));
    }

    /**
     * Crea festività ricorrenti per anni multipli.
     */
    public static function createRecurringForYears(string $name, string $monthDay, array $years): void
    {
        foreach ($years as $year) {
            $date = "$year-$monthDay";

            static::firstOrCreate([
                'holiday_date' => $date,
                'name' => $name,
                'is_recurring' => true,
            ]);
        }
    }

    /**
     * Ottieni il nome della festività per una data specifica.
     */
    public static function getHolidayName(\DateTime $date): ?string
    {
        $holiday = static::where('holiday_date', $date->format('Y-m-d'))
            ->orWhere(function ($query) use ($date) {
                $query->where('is_recurring', true)
                    ->whereRaw("DATE_FORMAT(holiday_date, '%m-%d') = ?", [$date->format('m-d')]);
            })
            ->first();

        return $holiday?->name;
    }
}
