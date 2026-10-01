<script setup lang="ts">
import { ReceiptText } from '@lucide/vue'

/**
 * `BillingProfileBanner` (SCREENS W27, W32): lists the fields still missing before the first payment
 * (`Organization.billing_profile_missing`, field paths such as `national_address.building_number`).
 */
const props = defineProps<{ missing: string[] }>()
const { t, te } = useI18n()

const labels = computed(() => props.missing.map((path) => {
  const field = path.split('.').at(-1) ?? path
  const key = `organization.fields.${field}`
  return te(key) ? t(key) : path
}))
</script>

<template>
  <UiAlert
    tone="info"
    :icon="ReceiptText"
    :title="t('organization.billing_profile.title')"
  >
    <p>{{ t('organization.billing_profile.body') }}</p>
    <ul
      v-if="labels.length > 0"
      class="mt-2 list-disc ps-5"
    >
      <li
        v-for="label in labels"
        :key="label"
      >
        {{ label }}
      </li>
    </ul>
    <slot />
  </UiAlert>
</template>
