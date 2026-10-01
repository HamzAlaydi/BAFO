<?php

declare(strict_types=1);

namespace App\Modules\Platform\Policies;

use App\Support\Files\File;
use App\Support\Files\FileAccessRegistry;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Private file downloads: the FileAccessRegistry rule of the file's purpose decides
 * (ARCHITECTURE §4.6, §8.5). A denial is 403 `forbidden`.
 */
final readonly class FilePolicy
{
    public function __construct(private FileAccessRegistry $registry) {}

    public function download(Authenticatable $user, File $file): Response
    {
        return $this->registry->allows($user, $file) ? Response::allow() : Response::deny();
    }
}
