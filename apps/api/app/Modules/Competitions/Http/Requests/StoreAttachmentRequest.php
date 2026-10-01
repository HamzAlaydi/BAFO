<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Http\Requests;

use App\Modules\Competitions\Enums\AttachmentKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * `POST /competitions/{competition}/attachments` (API.md §1.4):
 *
 *   file (multipart)  `file`, `kind` document | invitation_document, `title?`
 *   link (JSON)       `kind` = external_link, `url` (https), `title`
 *
 * The file type and size are checked by FileStorage (422 `file_type_not_allowed`, `file_too_large`).
 */
final class StoreAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $link = $this->input('kind') === AttachmentKind::ExternalLink->value;

        return [
            'kind' => ['required', Rule::in(['document', 'invitation_document', 'external_link'])],
            'file' => $link ? ['prohibited'] : ['required', 'file'],
            'url' => $link ? ['required', 'string', 'url:https', 'max:1000'] : ['prohibited'],
            'title' => [$link ? 'required' : 'nullable', 'string', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [];

        foreach (['kind', 'file', 'url', 'title'] as $field) {
            $value = __('competitions.attributes.'.$field);
            $attributes[$field] = is_string($value) ? $value : $field;
        }

        return $attributes;
    }

    public function kind(): AttachmentKind
    {
        return AttachmentKind::from($this->string('kind')->toString());
    }

    public function uploadedFile(): ?UploadedFile
    {
        $file = $this->file('file');

        return $file instanceof UploadedFile ? $file : null;
    }

    public function url(): ?string
    {
        $url = $this->input('url');

        return is_string($url) ? $url : null;
    }

    public function title(): ?string
    {
        $title = $this->input('title');

        return is_string($title) ? $title : null;
    }
}
