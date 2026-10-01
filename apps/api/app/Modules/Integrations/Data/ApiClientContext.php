<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Data;

use App\Modules\Integrations\Enums\ApiAuthMethod;
use App\Modules\Integrations\Enums\ApiScope;
use App\Modules\Integrations\Models\ApiClient;
use App\Modules\Integrations\Models\ApiKey;
use Illuminate\Http\Request;
use LogicException;

/**
 * The authenticated public API caller (ARCHITECTURE §14.2), set by `api.client` on the request:
 * attribute `api_client_context`, plus `api_client` (the model) and `api_scopes` (list of strings).
 */
final readonly class ApiClientContext
{
    public const string ATTRIBUTE = 'api_client_context';

    /**
     * @param  list<string>  $scopes  the effective scopes of this request
     */
    public function __construct(
        public ApiClient $client,
        public array $scopes,
        public ApiAuthMethod $method,
        public ?ApiKey $key = null,
    ) {}

    public static function from(Request $request): self
    {
        $context = $request->attributes->get(self::ATTRIBUTE);

        if (! $context instanceof self) {
            throw new LogicException('The public API client is not authenticated: is the `api.client` middleware missing?');
        }

        return $context;
    }

    public function allows(ApiScope $scope): bool
    {
        return in_array($scope->value, $this->scopes, true);
    }

    public function organizationId(): int
    {
        return $this->client->organization_id;
    }
}
