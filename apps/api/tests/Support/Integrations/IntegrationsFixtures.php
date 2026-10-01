<?php

declare(strict_types=1);

namespace Tests\Support\Integrations;

use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Modules\Integrations\Database\Factories\ApiKeyFactory;
use App\Modules\Integrations\Enums\ApiScope;
use App\Modules\Integrations\Models\ApiClient;
use App\Modules\Integrations\Models\ApiKey;
use App\Modules\Integrations\Services\Webhooks\HostResolver;
use Illuminate\Support\Facades\Auth;
use Laravel\Passport\ClientRepository;
use Laravel\Sanctum\Sanctum;

/**
 * Builders shared by the Integrations tests.
 */
final class IntegrationsFixtures
{
    /**
     * An issuer organization with a signed-in member of `$role` (owners and admins hold
     * `integrations.manage`; members do not).
     *
     * @return array{0: Organization, 1: User}
     */
    public static function signIn(OrgRole $role = OrgRole::Owner, bool $apiEnabled = true, ?Organization $organization = null): array
    {
        $organization ??= $apiEnabled ? Organization::factory()->apiEnabled()->create() : Organization::factory()->create();
        $user = User::factory()->withMembership($organization, $role)->create();

        Auth::forgetGuards();
        Sanctum::actingAs($user);

        return [$organization, $user];
    }

    /**
     * An active client with a Passport client-credentials client; returns the client and its plain
     * OAuth secret.
     *
     * @param  list<ApiScope>|null  $scopes  null = the dashboard defaults
     * @return array{0: ApiClient, 1: string}
     */
    public static function oauthClient(?Organization $organization = null, ?array $scopes = null): array
    {
        $passport = app(ClientRepository::class)->createClientCredentialsGrantClient('ERP');

        $factory = ApiClient::factory()->state(['oauth_client_id' => $passport->getKey()]);
        $factory = $organization !== null ? $factory->for($organization) : $factory;
        $factory = $scopes !== null ? $factory->scopes($scopes) : $factory;

        return [$factory->create(), (string) $passport->plainSecret];
    }

    /**
     * A plain API key of a new (or the given) client.
     *
     * @param  list<ApiScope>|null  $scopes
     * @return array{0: string, 1: ApiClient, 2: ApiKey}
     */
    public static function apiKey(?ApiClient $client = null, ?array $scopes = null, ?Organization $organization = null): array
    {
        if ($client === null) {
            $factory = ApiClient::factory();
            $factory = $organization !== null ? $factory->for($organization) : $factory;
            $client = ($scopes !== null ? $factory->scopes($scopes) : $factory)->create();
        }

        $plain = ApiKeyFactory::makePlainKey((string) config('bafo.integrations.key_environment'));
        $key = ApiKey::factory()->for($client)->withPlainKey($plain)->create();

        return [$plain, $client, $key];
    }

    /**
     * @return array<string, string>
     */
    public static function bearer(string $token): array
    {
        return ['Authorization' => 'Bearer '.$token];
    }

    public static function fakeDns(): FakeHostResolver
    {
        $resolver = new FakeHostResolver;
        app()->instance(HostResolver::class, $resolver);

        return $resolver;
    }
}
