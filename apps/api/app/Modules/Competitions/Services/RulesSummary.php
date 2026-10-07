<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Services;

use App\Modules\Competitions\Enums\Format;
use App\Modules\Competitions\Enums\MustBeat;
use App\Modules\Competitions\Enums\RankVisibility;
use App\Modules\Competitions\Models\Competition;
use App\Support\Money\Money;

/**
 * The localised rules sentences every surface shows (ARCHITECTURE §7.16), generated from the
 * columns. Keys: `competitions.rules_summary.*`. The reserve price is never mentioned to
 * participants; the issuer variant adds `reserve_hidden.{tender|auction}`. No sentence hard-codes "lowest" or
 * "highest": direction-specific text comes from the `.tender` / `.auction` variants.
 */
final class RulesSummary
{
    /**
     * @return list<string>
     */
    public static function lines(Competition $competition, string $locale, bool $issuer = false): array
    {
        $direction = $competition->direction->value;
        $lines = [self::text("type.{$direction}", $locale)];

        $lines[] = self::text($competition->format === Format::Sealed ? 'format_sealed' : 'format_live', $locale);

        if ($competition->start_price_minor !== null) {
            $lines[] = self::text("start_price.{$direction}", $locale, ['amount' => Money::format($competition->start_price_minor, $locale)]);
        }

        if ($competition->format === Format::Live) {
            if ($competition->must_beat === MustBeat::Best) {
                $lines[] = self::text('must_beat_best', $locale);
            } elseif ($competition->must_beat === MustBeat::Own) {
                $lines[] = self::text('must_beat_own', $locale);
            }

            if ($competition->min_step_minor !== null) {
                $lines[] = self::text('min_step_amount', $locale, ['amount' => Money::format($competition->min_step_minor, $locale)]);
            } elseif ($competition->min_step_bps !== null) {
                $lines[] = self::text('min_step_percent', $locale, ['percent' => self::percent($competition->min_step_bps)]);
            }
        }

        $prices = $competition->show_prices ? 'with_prices' : 'without_prices';
        $lines[] = match ($competition->rank_visibility) {
            RankVisibility::None => self::text('visibility_none', $locale),
            RankVisibility::LeadingFlag => self::text("visibility_leading_flag.{$prices}", $locale),
            RankVisibility::Full => self::text("visibility_full.{$prices}", $locale),
        };

        if ($competition->final_window_minutes !== null) {
            $lines[] = self::text('final_window', $locale, ['minutes' => self::counted('minutes', $competition->final_window_minutes, $locale)]);
        }

        if ($competition->auto_extend_enabled && $competition->auto_extend_window_seconds !== null
            && $competition->auto_extend_by_seconds !== null && $competition->auto_extend_max !== null) {
            $key = $competition->hard_stop_at !== null ? 'auto_extend_with_latest' : 'auto_extend';
            $lines[] = self::text($key, $locale, [
                'window' => self::duration($competition->auto_extend_window_seconds, $locale),
                'by' => self::duration($competition->auto_extend_by_seconds, $locale),
                'max' => self::counted('times', $competition->auto_extend_max, $locale),
                'latest' => $competition->hard_stop_at !== null ? DisplayTime::format($competition->hard_stop_at, $locale) : '',
            ]);
        }

        if ($competition->bafo_round_enabled) {
            $lines[] = self::text('bafo', $locale);
        }

        $lines[] = self::text('server_time', $locale);
        $lines[] = self::text('prices_excl_vat', $locale);

        if ($issuer && $competition->reserve_price_minor !== null) {
            // Tender «السعر المستهدف» / "Target price", auction «الحد الأدنى المقبول» / "Reserve price" (SCREENS §5).
            $lines[] = self::text("reserve_hidden.{$direction}", $locale, ['amount' => Money::format($competition->reserve_price_minor, $locale)]);
        }

        return $lines;
    }

    /**
     * Basis points as a percentage with up to 2 decimals (CONVENTIONS §9.2): 50 → "0.5".
     */
    private static function percent(int $bps): string
    {
        $whole = intdiv($bps, 100);
        $fraction = $bps % 100;

        return $fraction === 0 ? (string) $whole : $whole.'.'.rtrim(str_pad((string) $fraction, 2, '0', STR_PAD_LEFT), '0');
    }

    /**
     * Seconds as whole minutes, or as seconds-precise "m:ss" when not a whole minute.
     */
    private static function minutes(int $seconds): string
    {
        $remainder = $seconds % 60;

        return $remainder === 0 ? (string) intdiv($seconds, 60) : intdiv($seconds, 60).':'.str_pad((string) $remainder, 2, '0', STR_PAD_LEFT);
    }

    /**
     * A duration with its unit in the locale's plural form («3 دقائق», «دقيقتين», "1 minute"); a duration
     * that is not a whole number of minutes reads "m:ss" with the generic unit.
     */
    private static function duration(int $seconds, string $locale): string
    {
        return $seconds % 60 === 0
            ? self::counted('minutes', intdiv($seconds, 60), $locale)
            : self::text('units.minutes_exact', $locale, ['time' => self::minutes($seconds)]);
    }

    /**
     * A count with its noun in the locale's plural form (`competitions.rules_summary.units.<unit>`, six
     * Arabic forms: «مرة واحدة», «مرتين», «3 مرات», «20 مرة»).
     */
    private static function counted(string $unit, int $count, string $locale): string
    {
        return trans_choice('competitions.rules_summary.units.'.$unit, $count, ['count' => $count], $locale);
    }

    /**
     * @param  array<string, scalar>  $replace
     */
    private static function text(string $key, string $locale, array $replace = []): string
    {
        $text = __('competitions.rules_summary.'.$key, $replace, $locale);

        return is_string($text) ? $text : $key;
    }
}
