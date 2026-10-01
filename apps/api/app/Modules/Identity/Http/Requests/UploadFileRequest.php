<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Http\UploadedFile;
use LogicException;

/**
 * Multipart uploads of `POST /me/avatar`, `POST /organization/logo` and
 * `POST /organization/profile-document`. Only presence is validated here: FileStorage checks the
 * type and size of the purpose and answers `file_type_not_allowed` / `file_too_large` (422).
 */
final class UploadFileRequest extends IdentityRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file'],
        ];
    }

    public function upload(): UploadedFile
    {
        $file = $this->file('file');

        return $file instanceof UploadedFile ? $file : throw new LogicException('Validated upload missing.');
    }

    protected function attributeKeys(): array
    {
        return ['file' => 'file'];
    }
}
