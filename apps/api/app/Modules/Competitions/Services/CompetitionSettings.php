<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Services;

use App\Support\Settings\Settings;

/**
 * Typed access to the runtime settings the Competitions rules read (ARCHITECTURE §15.3).
 *
 * The `competitions.*` defaults are registered by this module. The `bidding.*` keys belong to
 * Bidding, which registers their defaults; the §15.3 defaults are repeated here as fallbacks so
 * the rules hold while that registration is missing.
 */
final readonly class CompetitionSettings
{
    public function __construct(private Settings $settings) {}

    public function minDurationMinutes(): int
    {
        return $this->int('competitions.min_duration_minutes', 10);
    }

    public function maxDurationDays(): int
    {
        return $this->int('competitions.max_duration_days', 90);
    }

    public function inviteCutoffMinutes(): int
    {
        return $this->int('competitions.invite_cutoff_minutes', 60);
    }

    public function maxParticipants(): int
    {
        return $this->int('competitions.max_participants', 200);
    }

    public function minExtendMinutes(): int
    {
        return $this->int('competitions.min_extend_minutes', 5);
    }

    /**
     * @return array{min: int, max: int}
     */
    public function finalWindowBounds(): array
    {
        $bounds = $this->array('competitions.final_window_bounds');

        return ['min' => $this->intFrom($bounds, 'min', 30), 'max' => $this->intFrom($bounds, 'max', 600)];
    }

    public function maxAmountMinor(): int
    {
        return $this->int('bidding.max_amount_minor', 1_000_000_000_000);
    }

    /**
     * @return array{window_min: int, window_max: int, by_min: int, by_max: int, max_min: int, max_max: int}
     */
    public function autoExtendBounds(): array
    {
        $bounds = $this->array('bidding.auto_extend_bounds');

        return [
            'window_min' => $this->intFrom($bounds, 'window_min', 60),
            'window_max' => $this->intFrom($bounds, 'window_max', 1800),
            'by_min' => $this->intFrom($bounds, 'by_min', 60),
            'by_max' => $this->intFrom($bounds, 'by_max', 1800),
            'max_min' => $this->intFrom($bounds, 'max_min', 1),
            'max_max' => $this->intFrom($bounds, 'max_max', 50),
        ];
    }

    /**
     * @return array{min: int, max: int}
     */
    public function bafoDurationBounds(): array
    {
        $bounds = $this->array('bidding.bafo_duration_bounds');

        return ['min' => $this->intFrom($bounds, 'min', 15), 'max' => $this->intFrom($bounds, 'max', 4320)];
    }

    public function maxConcurrentLive(): int
    {
        return $this->int('bidding.max_concurrent_live', 30);
    }

    /**
     * Closing-soon thresholds in minutes, largest first (setting `bidding.closing_soon_minutes`).
     *
     * @return list<int>
     */
    public function closingSoonMinutes(): array
    {
        $value = $this->settings->get('bidding.closing_soon_minutes', [10, 2]);
        $minutes = is_array($value) ? array_values(array_filter(array_map(
            static fn (mixed $m): int => is_numeric($m) ? (int) $m : 0,
            $value,
        ), static fn (int $m): bool => $m > 0)) : [10, 2];
        rsort($minutes);

        return $minutes;
    }

    public function resendPerDay(): int
    {
        $value = config('bafo.competitions.invitations.resend_per_day', 3);

        return is_numeric($value) ? (int) $value : 3;
    }

    private function int(string $key, int $default): int
    {
        $value = $this->settings->get($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function array(string $key): array
    {
        $value = $this->settings->get($key, []);

        return is_array($value) ? $value : [];
    }

    /**
     * @param  array<array-key, mixed>  $values
     */
    private function intFrom(array $values, string $key, int $default): int
    {
        $value = $values[$key] ?? $default;

        return is_numeric($value) ? (int) $value : $default;
    }
}
