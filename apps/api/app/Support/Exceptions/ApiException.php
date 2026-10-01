<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

use RuntimeException;
use Throwable;

/**
 * A domain error that renders as { message, code, errors } with the given HTTP status.
 *
 * The message is a translation key (resolved against the request locale at render time)
 * or a literal string. Throw it from services and actions with named arguments:
 *
 *     throw new ApiException('competition_closed', status: 409);             // message: errors.competition_closed
 *     throw new ApiException('sponsorship_payment_required', status: 409, details: ['quote' => $quote]);
 *
 * The body is { message, code, errors } plus "details" when details are given.
 */
class ApiException extends RuntimeException
{
    /**
     * @param  string  $errorCode  snake_case machine code, e.g. "offer_below_minimum_step"
     * @param  string|null  $messageKey  translation key or literal; defaults to "errors.{code}"
     * @param  array<string, list<string>>  $errors  field errors, same shape as validation errors
     * @param  array<string, scalar>  $replace  translation placeholders
     * @param  array<string, string>  $headers  extra response headers
     * @param  array<string, mixed>  $details  machine-readable context, rendered as "details"
     */
    public function __construct(
        public readonly string $errorCode,
        public readonly ?string $messageKey = null,
        public readonly int $status = 400,
        public readonly array $errors = [],
        public readonly array $replace = [],
        public readonly array $headers = [],
        ?Throwable $previous = null,
        public readonly array $details = [],
    ) {
        parent::__construct($messageKey ?? $errorCode, 0, $previous);
    }

    public function translatedMessage(): string
    {
        $key = $this->messageKey ?? 'errors.'.$this->errorCode;
        $message = __($key, $this->replace);

        return is_string($message) ? $message : $key;
    }

    /**
     * Client errors are expected behaviour and are not reported.
     */
    public function shouldReport(): bool
    {
        return $this->status >= 500;
    }
}
