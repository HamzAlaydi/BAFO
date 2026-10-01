<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Competitions module configuration: config('bafo.competitions.*')
|--------------------------------------------------------------------------
|
| Runtime settings (ARCHITECTURE §15.3): the defaults below are registered with
| Settings::defaults() by CompetitionsServiceProvider; admins edit them in app_settings.
| Read them through App\Modules\Competitions\Services\CompetitionSettings.
|
*/

return [

    'settings' => [
        'competitions.min_duration_minutes' => 10,
        'competitions.max_duration_days' => 90,
        'competitions.invite_cutoff_minutes' => 60,
        'competitions.max_participants' => 200,
        'competitions.min_extend_minutes' => 5,
        'competitions.final_window_bounds' => ['min' => 30, 'max' => 600],
    ],

    // Invitation resend limit (API.md §1.4: at most 3 per invitation per day).
    'invitations' => [
        'resend_per_day' => 3,
    ],

];
