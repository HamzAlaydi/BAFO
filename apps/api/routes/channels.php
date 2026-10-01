<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Broadcast channels (Laravel Reverb, private channels only)
|--------------------------------------------------------------------------
|
| Auth endpoint: POST /broadcasting/auth with "Authorization: Bearer <Sanctum token>"
| (bootstrap/app.php withBroadcasting, middleware auth:sanctum). Channel names use
| public ids (ULIDs), never internal keys (ARCHITECTURE §9.2):
|
|   competition.{competition}                             issuer side      (Bidding)
|   competition.{competition}.participant.{organization}  one participant  (Bidding)
|   user.{user}                                           one user         (Notifications)
|
| This file stays empty: each owning module registers its channels with
| Broadcast::channel() in its service provider's boot().
|
*/
