<?php

declare(strict_types=1);

namespace Tests\Support\Platform;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\Sanctum;

/**
 * An unsaved stand-in for the Identity user in kernel tests: enough for Sanctum::actingAs(),
 * ResolveActor and FileAccessRegistry rules, without the Identity schema.
 *
 * Kernel tests only. Module tests use the real user: `User::factory()->withMembership($org,
 * OrgRole::Owner)->create()` with `Sanctum::actingAs($user)` or a real bearer token
 * (tests/Feature/Platform/FoundationIntegrationTest.php). It has no morph alias, so it cannot
 * own tokens, audit subjects or notifications.
 */
final class TestUser extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'users';

    /**
     * @var list<string>
     */
    protected $fillable = ['id', 'name', 'email', 'organization_id'];

    public static function make(int $id = 1, ?int $organizationId = null, string $name = 'Test User'): self
    {
        $user = new self;
        $user->forceFill([
            'id' => $id,
            'name' => $name,
            'email' => "user{$id}@example.test",
            'organization_id' => $organizationId,
        ]);
        $user->exists = true;

        return $user;
    }

    /**
     * Authenticates the next requests as this user (bearer token semantics).
     */
    public static function actingAs(int $id = 1, ?int $organizationId = null): self
    {
        $user = self::make($id, $organizationId);
        Sanctum::actingAs($user);

        return $user;
    }
}
