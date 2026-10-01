<script setup lang="ts">
import { ArrowLeft, ArrowRight } from '@lucide/vue'

/**
 * `BillingProfileGate` (SCREENS W32): before the first payment the organization's legal and tax
 * data must be complete. Lists the missing fields and links to the organization page, which returns
 * here (`?return=`) after a save that completes the profile.
 */
const props = defineProps<{
  missing: string[]
  /** Current localised path to come back to. */
  returnTo: string
  canEdit: boolean
}>()

const { t } = useI18n()
const locale = useAppLocale()
const forward = computed(() => (locale.value === 'ar' ? ArrowLeft : ArrowRight))
const target = computed(() => ({ path: '/dashboard/organization', query: { return: props.returnTo } }))
</script>

<template>
  <OrganizationBillingProfileBanner :missing="missing">
    <div class="mt-3 flex flex-col gap-2">
      <UiButton
        v-if="canEdit"
        size="sm"
        class="self-start"
        :to="target"
        :icon-end="forward"
      >
        {{ t('billing.checkout.profile_gate.complete') }}
      </UiButton>
      <p
        v-else
        class="text-sm"
      >
        {{ t('billing.checkout.profile_gate.ask_owner') }}
      </p>
    </div>
  </OrganizationBillingProfileBanner>
</template>
