<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Data;

use App\Modules\Integrations\Models\ApiClient;

/**
 * A client together with its plain OAuth `client_secret`, which is shown once (create, rotate).
 */
final readonly class IssuedApiClient
{
    public function __construct(
        public ApiClient $client,
        public string $clientSecret,
    ) {}
}
