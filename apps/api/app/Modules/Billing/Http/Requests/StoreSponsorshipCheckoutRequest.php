<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Requests;

use App\Modules\Billing\Enums\SponsorshipIntent;
use App\Support\Http\Middleware\IdempotentRequest;
use Illuminate\Validation\Rule;

/**
 * `POST /competitions/{competition}/sponsorship/checkout` (API.md §1.7). For `intent: invite`
 * the rows follow the `POST …/invitations` rules; the per-row business checks (duplicates, own
 * organization, vendors) run in the Action and answer with `details.item_codes`.
 */
final class StoreSponsorshipCheckoutRequest extends BillingFormRequest
{
    public function rules(): array
    {
        return [
            'intent' => ['required', Rule::enum(SponsorshipIntent::class)],
            'invitations' => ['required_if:intent,invite', 'prohibited_unless:intent,invite', 'array', 'min:1', 'max:100'],
            'invitations.*' => ['array'],
            'invitations.*.email' => ['required_without_all:invitations.*.organization_id,invitations.*.vendor_id', 'nullable', 'string', 'email', 'max:255'],
            'invitations.*.organization_id' => ['nullable', 'string', 'max:40'],
            'invitations.*.vendor_id' => ['nullable', 'string', 'max:40'],
            'invitations.*.name' => ['nullable', 'string', 'max:150'],
            'coupon_code' => ['nullable', 'string', 'max:40'],
            'return_url' => ['required', 'string', 'url', 'max:1000'],
        ];
    }

    public function intent(): SponsorshipIntent
    {
        return SponsorshipIntent::from((string) $this->validated('intent'));
    }

    /**
     * @return list<array{email?: string|null, organization_id?: string|null, vendor_id?: string|null, name?: string|null}>
     */
    public function rows(): array
    {
        $rows = $this->validated('invitations');

        if (! is_array($rows)) {
            return [];
        }

        // validated() assembles wildcard data rule by rule, so the rows may come back out of
        // their input order (numeric keys kept): restore it, the item-code paths depend on it.
        ksort($rows);
        $clean = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $clean[] = [
                'email' => isset($row['email']) && is_string($row['email']) ? $row['email'] : null,
                'organization_id' => isset($row['organization_id']) && is_string($row['organization_id']) ? $row['organization_id'] : null,
                'vendor_id' => isset($row['vendor_id']) && is_string($row['vendor_id']) ? $row['vendor_id'] : null,
                'name' => isset($row['name']) && is_string($row['name']) ? $row['name'] : null,
            ];
        }

        return $clean;
    }

    public function couponCode(): ?string
    {
        return $this->stringOrNull('coupon_code');
    }

    public function returnUrl(): string
    {
        return (string) $this->validated('return_url');
    }

    public function idempotencyKey(): ?string
    {
        $key = trim((string) $this->header(IdempotentRequest::HEADER, ''));

        return $key === '' ? null : mb_substr($key, 0, 64);
    }
}
