<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Http\Requests\Concerns;

use App\Modules\Identity\Enums\OrganizationStatus;
use App\Modules\Identity\Models\Organization;
use App\Modules\Integrations\Models\ExternalRef;
use App\Modules\Integrations\Models\Vendor;
use App\Support\Features\Feature;
use App\Support\Features\FeatureFlags;
use App\Support\Features\FeatureRefusals;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The `invitations` rows of API.md §1.4 (`POST …/invitations`) and §3.4 (`InvitationInput`,
 * also inside the public `POST /competitions`): an e-mail, an organization (from suggestions),
 * a vendor of the issuer, or a vendor by ERP key (`vendor_external`), plus `name` and
 * `sponsored`.
 *
 * Unknown vendors are not field errors here: InviteParticipants reports them with the item code
 * `vendor_not_found`.
 */
trait ValidatesInvitationRows
{
    /**
     * @return array<string, mixed>
     */
    protected function invitationRules(bool $required): array
    {
        return [
            'invitations' => [$required ? 'required' : 'sometimes', 'array', 'min:1', 'max:100'],
            'invitations.*' => ['array'],
            'invitations.*.email' => ['required_without_all:invitations.*.organization_id,invitations.*.vendor_id,invitations.*.vendor_external', 'nullable', 'string', 'email', 'max:255'],
            'invitations.*.organization_id' => ['nullable', 'string', Rule::exists('organizations', 'public_id')
                ->where('status', OrganizationStatus::Active->value)->whereNull('deleted_at')],
            'invitations.*.vendor_id' => ['nullable', 'string', 'max:26'],
            'invitations.*.vendor_external' => ['nullable', 'array'],
            'invitations.*.vendor_external.system' => ['required_with:invitations.*.vendor_external', 'string', 'max:60'],
            'invitations.*.vendor_external.id' => ['required_with:invitations.*.vendor_external', 'string', 'max:120'],
            'invitations.*.name' => ['nullable', 'string', 'max:150'],
            'invitations.*.sponsored' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Exactly one of `email`, `organization_id`, `vendor_id` and `vendor_external` per row.
     */
    protected function checkInvitationTargets(Validator $validator): void
    {
        foreach ((array) $this->input('invitations', []) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $given = array_filter(['email', 'organization_id', 'vendor_id', 'vendor_external'],
                static fn (string $key): bool => isset($row[$key]) && $row[$key] !== '' && $row[$key] !== []);

            if (count($given) > 1) {
                $message = __('competitions.validation.invitation_one_target');
                $validator->errors()->add("invitations.{$index}", is_string($message) ? $message : 'invitation_one_target');
            }
        }
    }

    /**
     * RELEASE_SCOPE.md §1.5 field-level refusals (422 `errors.feature_disabled_field` on the row
     * path): a vendor reference (`vendor_id`, `vendor_external`) while `vendor_directory` is off,
     * and `sponsored = true` while `sponsorship` is off.
     */
    protected function refuseHiddenInvitationFields(Validator $validator): void
    {
        $features = app(FeatureFlags::class);
        $vendors = $features->enabled(Feature::VendorDirectory);
        $sponsorship = $features->enabled(Feature::Sponsorship);

        if ($vendors && $sponsorship) {
            return;
        }

        foreach ((array) $this->input('invitations', []) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            foreach ($vendors ? [] : ['vendor_id', 'vendor_external'] as $key) {
                if (isset($row[$key]) && $row[$key] !== '' && $row[$key] !== []) {
                    FeatureRefusals::refuseField($validator, "invitations.{$index}.{$key}");
                }
            }

            if (! $sponsorship && in_array($row['sponsored'] ?? null, [true, 1, '1'], true)) {
                FeatureRefusals::refuseField($validator, "invitations.{$index}.sponsored");
            }
        }
    }

    /**
     * @return array<string, string>
     */
    protected function invitationAttributes(): array
    {
        $attributes = [];

        foreach ([
            'invitations' => 'invitations',
            'invitations.*.email' => 'email',
            'invitations.*.organization_id' => 'organization_id',
            'invitations.*.vendor_id' => 'vendor_id',
            'invitations.*.vendor_external' => 'vendor_id',
            'invitations.*.name' => 'name',
            'invitations.*.sponsored' => 'sponsored',
        ] as $field => $key) {
            $value = __('competitions.attributes.'.$key);
            $attributes[$field] = is_string($value) ? $value : $key;
        }

        return $attributes;
    }

    /**
     * The validated rows for InviteParticipants, with internal ids. An unknown vendor becomes id
     * 0, which the Action reports as `vendor_not_found`.
     *
     * @return list<array{email?: string|null, name?: string|null, organization_id?: int|null, vendor_id?: int|null, sponsored?: bool}>
     */
    public function rows(int $issuerOrganizationId): array
    {
        $rows = [];

        // validated() rebuilds nested arrays rule by rule, which can reorder the rows: restore
        // the request order, since item errors are keyed by the row index.
        $validated = (array) $this->validated('invitations', []);
        ksort($validated);

        foreach ($validated as $row) {
            if (! is_array($row)) {
                continue;
            }

            $item = [
                'name' => isset($row['name']) && is_string($row['name']) ? $row['name'] : null,
                'sponsored' => (bool) ($row['sponsored'] ?? false),
            ];

            if (isset($row['vendor_id']) && is_string($row['vendor_id'])) {
                $item['vendor_id'] = (int) (Vendor::query()->where('public_id', strtolower($row['vendor_id']))->value('id') ?? 0);
            } elseif (isset($row['vendor_external']) && is_array($row['vendor_external'])) {
                $item['vendor_id'] = (int) (ExternalRef::query()
                    ->where('organization_id', $issuerOrganizationId)
                    ->where('refable_type', 'vendor')
                    ->where('type', 'supplier')
                    ->where('system', (string) ($row['vendor_external']['system'] ?? ''))
                    ->where('value', (string) ($row['vendor_external']['id'] ?? ''))
                    ->value('refable_id') ?? 0);
            } elseif (isset($row['organization_id']) && is_string($row['organization_id'])) {
                $item['organization_id'] = (int) Organization::query()->where('public_id', strtolower($row['organization_id']))->value('id');
            } else {
                $item['email'] = isset($row['email']) && is_string($row['email']) ? $row['email'] : null;
            }

            $rows[] = $item;
        }

        return $rows;
    }
}
