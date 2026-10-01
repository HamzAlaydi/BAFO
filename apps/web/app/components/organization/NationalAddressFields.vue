<script setup lang="ts">
import type { NationalAddressForm } from '~/utils/organization-form'

/**
 * Saudi national address (API.md §1.3): building number (4 digits), street, district, postal code
 * (5 digits), additional number (4 digits) and short address (4 letters + 4 digits). Codes and numbers
 * are LTR islands. `errors` is keyed by part name.
 */
defineProps<{
  errors?: Partial<Record<keyof NationalAddressForm, string | undefined>>
  disabled?: boolean
  /** Parts listed as missing for the billing profile get a hint. */
  highlight?: ReadonlyArray<keyof NationalAddressForm>
}>()

const model = defineModel<NationalAddressForm>({ required: true })
const { t } = useI18n()

function update<K extends keyof NationalAddressForm>(key: K, value: string): void {
  model.value = { ...model.value, [key]: value }
}
</script>

<template>
  <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
    <UiInput
      :model-value="model.building_number"
      :label="t('organization.fields.building_number')"
      :hint="highlight?.includes('building_number') ? t('organization.billing_profile.required_hint') : t('organization.fields.building_number_hint')"
      inputmode="numeric"
      dir="ltr"
      :maxlength="4"
      :disabled="disabled"
      :error="errors?.building_number"
      @update:model-value="update('building_number', $event)"
    />
    <UiInput
      :model-value="model.additional_number"
      :label="t('organization.fields.additional_number')"
      :hint="t('organization.fields.additional_number_hint')"
      inputmode="numeric"
      dir="ltr"
      :maxlength="4"
      :disabled="disabled"
      :error="errors?.additional_number"
      @update:model-value="update('additional_number', $event)"
    />
    <UiInput
      :model-value="model.street"
      :label="t('organization.fields.street')"
      :hint="highlight?.includes('street') ? t('organization.billing_profile.required_hint') : undefined"
      :maxlength="150"
      :disabled="disabled"
      :error="errors?.street"
      @update:model-value="update('street', $event)"
    />
    <UiInput
      :model-value="model.district"
      :label="t('organization.fields.district')"
      :hint="highlight?.includes('district') ? t('organization.billing_profile.required_hint') : undefined"
      :maxlength="150"
      :disabled="disabled"
      :error="errors?.district"
      @update:model-value="update('district', $event)"
    />
    <UiInput
      :model-value="model.postal_code"
      :label="t('organization.fields.postal_code')"
      :hint="highlight?.includes('postal_code') ? t('organization.billing_profile.required_hint') : t('organization.fields.postal_code_hint')"
      inputmode="numeric"
      dir="ltr"
      :maxlength="5"
      :disabled="disabled"
      :error="errors?.postal_code"
      @update:model-value="update('postal_code', $event)"
    />
    <UiInput
      :model-value="model.short_address"
      :label="t('organization.fields.short_address')"
      :hint="t('organization.fields.short_address_hint')"
      dir="ltr"
      autocapitalize="characters"
      :maxlength="8"
      :disabled="disabled"
      :error="errors?.short_address"
      @update:model-value="update('short_address', $event.toUpperCase())"
    />
  </div>
</template>
