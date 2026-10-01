<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Http\Requests;

use App\Modules\Integrations\Enums\ImportMode;
use App\Modules\Integrations\Enums\ImportType;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * `POST /integrations/imports` (API.md §1.9): multipart `file` (csv or xlsx; the type and the
 * 20 MB limit are enforced by FileStorage, 422 `file_type_not_allowed` / `file_too_large`),
 * `type` (vendors), `mode` (validate|commit).
 */
final class StoreImportRequest extends IntegrationsRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file'],
            'type' => ['required', 'string', Rule::enum(ImportType::class)],
            'mode' => ['required', 'string', Rule::enum(ImportMode::class)],
        ];
    }

    public function upload(): UploadedFile
    {
        /** @var UploadedFile $file */
        $file = $this->file('file');

        return $file;
    }

    public function importType(): ImportType
    {
        return ImportType::from((string) $this->validated('type'));
    }

    public function mode(): ImportMode
    {
        return ImportMode::from((string) $this->validated('mode'));
    }
}
