<?php

declare(strict_types=1);

namespace App\Support\Database\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Microsecond timestamps (ARCHITECTURE §4.3) for the models of the precision-6 tables:
 * competitions, competition_extensions, offers, offer_rejections, offer_voids,
 * participant_standings, competition_live_states, bafo_rounds and awards.
 *
 * Their migrations use timestampsTz(6) / timestampTz(..., 6). Values read back from
 * PostgreSQL without a fraction still parse (Eloquent falls back to Carbon::parse()).
 *
 * @mixin Model
 */
trait UsesPreciseTimestamps
{
    /**
     * @var string
     */
    protected $dateFormat = 'Y-m-d H:i:s.uP';
}
