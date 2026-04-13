<?php

use App\Models\Holiday;
use App\Services\SlaService;
use Carbon\Carbon;

beforeEach(function () {
    $this->service = new SlaService();
});

it('calculates deadline correctly for same-day completion', function () {
    $start = Carbon::parse('2026-04-13 09:00:00'); // Monday
    $minutes = 60;

    $deadline = $this->service->calculateBusinessDeadline($start, $minutes);

    expect($deadline->toDateTimeString())->toBe('2026-04-13 10:00:00');
});

it('calculates deadline correctly for multi-day completion', function () {
    $start = Carbon::parse('2026-04-13 23:00:00'); // Monday late night
    $minutes = 120; // 2 hours

    $deadline = $this->service->calculateBusinessDeadline($start, $minutes);

    // Should continue to next business day
    expect($deadline->day)->toBeGreaterThanOrEqual(13);
});

it('skips weekends when calculating deadlines', function () {
    // Start on Friday late afternoon
    $start = Carbon::parse('2026-04-10 15:00:00'); // Friday
    $minutes = 48 * 60; // 48 hours

    $deadline = $this->service->calculateBusinessDeadline($start, $minutes);

    // Should skip Saturday and Sunday
    expect($deadline->dayOfWeek)->not->toBe(Carbon::SATURDAY);
    expect($deadline->dayOfWeek)->not->toBe(Carbon::SUNDAY);
});

it('skips Italian holidays when calculating deadlines', function () {
    // Create a holiday in the database
    Holiday::create([
        'name' => 'Test Holiday',
        'holiday_date' => '2026-04-14',
        'is_recurring' => false,
    ]);

    $start = Carbon::parse('2026-04-13 23:00:00'); // Day before holiday
    $minutes = 120;

    $deadline = $this->service->calculateBusinessDeadline($start, $minutes);

    // Should skip the holiday
    expect($deadline->toDateString())->not->toBe('2026-04-14');
});

it('returns correct Italian holidays for a given year', function () {
    $holidays = $this->service->getItalianHolidays(2026);

    expect($holidays)->toBeArray();
    expect($holidays)->toContain('2026-01-01'); // Capodanno
    expect($holidays)->toContain('2026-12-25'); // Natale
    expect($holidays)->toContain('2026-12-26'); // S. Stefano
    expect($holidays)->toContain('2026-04-25'); // Liberazione
    expect($holidays)->toContain('2026-05-01'); // Lavoro
    expect($holidays)->toContain('2026-06-02'); // Repubblica
    expect($holidays)->toContain('2026-08-15'); // Ferragosto
});

it('includes Easter Monday in Italian holidays', function () {
    $holidays = $this->service->getItalianHolidays(2026);

    // Easter 2026 is April 5, so Pasquetta is April 6
    expect($holidays)->toContain('2026-04-06');
});

it('handles zero minutes correctly', function () {
    $start = Carbon::parse('2026-04-13 09:00:00');
    $minutes = 0;

    $deadline = $this->service->calculateBusinessDeadline($start, $minutes);

    expect($deadline->toDateTimeString())->toBe('2026-04-13 09:00:00');
});

it('handles large minute values correctly', function () {
    $start = Carbon::parse('2026-04-13 09:00:00');
    $minutes = 10080; // 7 days in minutes

    $deadline = $this->service->calculateBusinessDeadline($start, $minutes);

    // Should be approximately 2 weeks later (skipping weekends)
    expect($deadline->greaterThan($start))->toBeTrue();
    expect($deadline->diffInDays($start))->toBeGreaterThanOrEqual(14);
});

it('starts counting from the exact start time', function () {
    $start = Carbon::parse('2026-04-13 14:30:00');
    $minutes = 30;

    $deadline = $this->service->calculateBusinessDeadline($start, $minutes);

    expect($deadline->toDateTimeString())->toBe('2026-04-13 15:00:00');
});

it('correctly handles overnight deadlines', function () {
    $start = Carbon::parse('2026-04-13 23:30:00');
    $minutes = 60;

    $deadline = $this->service->calculateBusinessDeadline($start, $minutes);

    // Should continue to next day
    expect($deadline->toDateString())->toBe('2026-04-14');
    expect($deadline->format('H:i:s'))->toBe('00:30:00');
});
