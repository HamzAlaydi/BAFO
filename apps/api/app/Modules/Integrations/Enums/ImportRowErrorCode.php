<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Enums;

/**
 * Row error codes of the vendor import (ARCHITECTURE §14.7, CONVENTIONS §8.1), stored in
 * `import_jobs.errors_preview[].code` and the errors file.
 */
enum ImportRowErrorCode: string
{
    case Required = 'required';
    case InvalidFormat = 'invalid_format';
    case UnknownRegion = 'unknown_region';
    case UnknownCategory = 'unknown_category';
    case DuplicateInFile = 'duplicate_in_file';
    // CONTRACT-GAP: §14.7 lists five codes. A row matched by its ERP key whose e-mail already
    // belongs to another vendor of the organization cannot be applied; it reuses the name of the
    // top-level code `vendor_email_taken`.
    case VendorEmailTaken = 'vendor_email_taken';

    /** The localised row message (`integrations.import.row_errors.<code>`). */
    public function message(string $column, ?string $locale = null): string
    {
        $message = __('integrations.import.row_errors.'.$this->value, ['column' => $column], $locale);

        return is_string($message) ? $message : $this->value;
    }

    /** Label in the given locale (default: the app locale). Key: `integrations.enums.import_row_error_code.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('integrations.enums.import_row_error_code.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
