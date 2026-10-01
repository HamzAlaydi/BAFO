<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Http\Controllers\PublicV1;

use App\Modules\Integrations\Services\AccessTokenIssuer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `POST /api/public/v1/oauth/token` (ARCHITECTURE §14.2, API.md §3.1): the client-credentials
 * grant. The only public response without the `{data}` envelope (RFC 6749 §5.1); errors carry
 * both shapes (ShapeOAuthErrors).
 *
 * CONTRACT-GAP: no FormRequest: the input is form or JSON with HTTP Basic, and every failure has
 * an OAuth error code (`unsupported_grant_type`, `invalid_client`, `invalid_scope`) instead of
 * `validation_failed`.
 */
final class IssueAccessTokenController
{
    public function __invoke(Request $request, AccessTokenIssuer $issuer): JsonResponse
    {
        return new JsonResponse($issuer->issue($request), 200, [
            'Cache-Control' => 'no-store',
            'Pragma' => 'no-cache',
        ], JSON_UNESCAPED_SLASHES);
    }
}
