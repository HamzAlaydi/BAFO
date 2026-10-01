<?php

declare(strict_types=1);

namespace App\Support\Auth;

/**
 * Who performed an action (ARCHITECTURE §4.1). Stored in audit_logs.actor_type.
 */
enum ActorType: string
{
    case User = 'user';
    case ApiClient = 'api_client';
    case Admin = 'admin';
    case System = 'system';
    case Guest = 'guest';
}
