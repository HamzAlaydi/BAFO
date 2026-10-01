<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Data;

use App\Modules\Integrations\Models\ApiKey;

/**
 * A new API key together with its plain value, which is shown once (ARCHITECTURE §14.1).
 */
final readonly class IssuedApiKey
{
    public function __construct(
        public ApiKey $key,
        public string $plainKey,
    ) {}
}
