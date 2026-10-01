<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\AppV1;

use App\Support\Files\File;
use App\Support\Files\FileStorage;
use App\Support\Http\Controllers\ApiController;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * GET /api/app/v1/files/{file}/download (user): streams a file as an attachment once the
 * FileAccessRegistry rule of its purpose allows the user (ARCHITECTURE §4.6, D11). Unknown or
 * malformed id → 404; rule denies → 403.
 */
final class DownloadFileController extends ApiController
{
    public function __invoke(File $file, FileStorage $storage): StreamedResponse
    {
        $this->authorize('download', $file);

        return $storage->download($file);
    }
}
