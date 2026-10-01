<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Controllers\Web;

use App\Modules\Notifications\Support\MailBranding;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves the brand mark that the mail header links to (`GET /mail/brand/bafo-mark.png`).
 * Public, cacheable, no session.
 *
 * CONTRACT-GAP: mail clients need an absolute image URL and `public/` is a shared (Platform)
 * path, so the module serves its own copy of the mark from `Resources/brand`.
 */
final class MailAssetController
{
    public function logo(): BinaryFileResponse
    {
        return response()->file(MailBranding::logoFile(), [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=604800, immutable',
        ]);
    }
}
