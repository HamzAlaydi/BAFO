<?php

declare(strict_types=1);

namespace App\Support\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;

/**
 * Who is acting, and through which channel (ARCHITECTURE §4.1). Every Action receives one;
 * AuditLogger stores it. Build it with the static constructors, never by hand in a module.
 *
 * The kernel does not depend on module classes, so the constructors take the model as
 * `Model` and read the attributes the contract defines:
 *
 *   forUser(Identity User)        organization = the user's membership (one per user, §5.3)
 *   forApiClient(Integrations ApiClient)   organization = api_clients.organization_id
 *   forAdmin(Admin Admin)         no organization
 */
final readonly class Actor
{
    public function __construct(
        public ActorType $type,
        public ?int $id,
        public ?int $organizationId,
        public ?int $userId,
        public ?int $apiClientId,
        public ?int $adminId,
        public Channel $channel,
        public ?string $ip = null,
        public ?string $userAgent = null,
        public ?string $requestId = null,
        // CONTRACT-GAP: §4.1 lists no display label; AuditLogger needs audit_logs.actor_label
        // (name/e-mail snapshot) without loading module models, so the constructors capture it.
        public ?string $label = null,
    ) {}

    public static function system(): self
    {
        return new self(
            type: ActorType::System,
            id: null,
            organizationId: null,
            userId: null,
            apiClientId: null,
            adminId: null,
            channel: Channel::System,
            requestId: self::contextRequestId(),
            label: 'system',
        );
    }

    public static function guest(?Request $request = null): self
    {
        return new self(
            type: ActorType::Guest,
            id: null,
            organizationId: null,
            userId: null,
            apiClientId: null,
            adminId: null,
            channel: Channel::fromPlatformHeader($request?->header('X-Platform')),
            ip: $request?->ip(),
            userAgent: self::userAgent($request),
            requestId: self::requestId($request),
        );
    }

    /**
     * A signed-in user of the first-party apps. The channel comes from X-Platform.
     *
     * @param  Model&Authenticatable  $user  App\Modules\Identity\Models\User
     */
    public static function forUser(Model&Authenticatable $user, ?Request $request = null): self
    {
        $id = self::intOrNull($user->getKey());

        return new self(
            type: ActorType::User,
            id: $id,
            organizationId: self::userOrganizationId($user),
            userId: $id,
            apiClientId: null,
            adminId: null,
            channel: Channel::fromPlatformHeader($request?->header('X-Platform')),
            ip: $request?->ip(),
            userAgent: self::userAgent($request),
            requestId: self::requestId($request),
            label: self::labelOf($user),
        );
    }

    /**
     * A public API client (set by Integrations' `api.client` middleware).
     *
     * @param  Model  $client  App\Modules\Integrations\Models\ApiClient
     */
    public static function forApiClient(Model $client, ?Request $request = null): self
    {
        $id = self::intOrNull($client->getKey());

        return new self(
            type: ActorType::ApiClient,
            id: $id,
            organizationId: self::intOrNull($client->getAttribute('organization_id')),
            userId: null,
            apiClientId: $id,
            adminId: null,
            channel: Channel::Api,
            ip: $request?->ip(),
            userAgent: self::userAgent($request),
            requestId: self::requestId($request),
            label: self::labelOf($client),
        );
    }

    /**
     * A platform admin acting in the Filament panel.
     *
     * @param  Model  $admin  App\Modules\Admin\Models\Admin
     */
    public static function forAdmin(Model $admin, ?Request $request = null): self
    {
        $id = self::intOrNull($admin->getKey());

        return new self(
            type: ActorType::Admin,
            id: $id,
            organizationId: null,
            userId: null,
            apiClientId: null,
            adminId: $id,
            channel: Channel::Admin,
            ip: $request?->ip(),
            userAgent: self::userAgent($request),
            requestId: self::requestId($request),
            label: self::labelOf($admin),
        );
    }

    public function isUser(): bool
    {
        return $this->type === ActorType::User;
    }

    public function isApiClient(): bool
    {
        return $this->type === ActorType::ApiClient;
    }

    public function isAdmin(): bool
    {
        return $this->type === ActorType::Admin;
    }

    public function isSystem(): bool
    {
        return $this->type === ActorType::System;
    }

    public function isGuest(): bool
    {
        return $this->type === ActorType::Guest;
    }

    /**
     * The organization of the user: v1 has exactly one membership per user (§5.3). Identity's
     * User exposes it as the `membership` relation; an `organization_id` attribute also works.
     */
    private static function userOrganizationId(Model $user): ?int
    {
        $direct = self::intOrNull($user->getAttribute('organization_id'));

        if ($direct !== null || ! method_exists($user, 'membership') || ! $user->exists) {
            return $direct;
        }

        $membership = $user->getRelationValue('membership');

        return $membership instanceof Model ? self::intOrNull($membership->getAttribute('organization_id')) : null;
    }

    private static function labelOf(Model $model): ?string
    {
        foreach (['name', 'email'] as $attribute) {
            $value = $model->getAttribute($attribute);

            if (is_string($value) && $value !== '') {
                return mb_substr($value, 0, 255);
            }
        }

        return null;
    }

    private static function userAgent(?Request $request): ?string
    {
        $agent = $request?->userAgent();

        return $agent === null || $agent === '' ? null : mb_substr($agent, 0, 500);
    }

    private static function requestId(?Request $request): ?string
    {
        $id = $request?->attributes->get('request_id');

        return is_string($id) ? $id : self::contextRequestId();
    }

    /**
     * The request id propagated to queued jobs through Laravel Context (AssignRequestId).
     */
    private static function contextRequestId(): ?string
    {
        $id = Context::get('request_id');

        return is_string($id) ? $id : null;
    }

    private static function intOrNull(mixed $value): ?int
    {
        return is_int($value) ? $value : (is_numeric($value) ? (int) $value : null);
    }
}
