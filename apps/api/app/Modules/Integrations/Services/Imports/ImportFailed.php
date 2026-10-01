<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services\Imports;

use RuntimeException;

/**
 * The whole file cannot be imported (no header, a required column missing, too many rows). The
 * job ends `failed` with the localised `failure_message`.
 */
final class ImportFailed extends RuntimeException
{
    /**
     * @param  array<string, string>  $replace
     */
    public function __construct(public readonly string $messageKey, public readonly array $replace = [])
    {
        parent::__construct($messageKey);
    }

    public function localisedMessage(?string $locale = null): string
    {
        $message = __($this->messageKey, $this->replace, $locale);

        return is_string($message) ? $message : $this->messageKey;
    }
}
