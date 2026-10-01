<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Http\Controllers\Web;

use Illuminate\Contracts\View\View;

/**
 * `GET /docs/api` (ARCHITECTURE §14.9): the Scalar API reference over `openapi.json`.
 */
final class ApiDocsController
{
    public function __invoke(): View
    {
        return view('integrations::docs', [
            'specUrl' => url('/api/public/v1/openapi.json'),
            'yamlUrl' => url('/api/public/v1/openapi.yaml'),
        ]);
    }
}
