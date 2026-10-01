<script setup lang="ts">
import { fetchSponsorshipQuote } from '~/services/billing'
import type { IssuerCompetition } from '~/types/api/competitions'
import { draftProgressOf, firstIncompleteStep } from '~/stores/competition-editor-steps'

/**
 * `/dashboard/competitions/{id}/setup`: opens the draft wizard at the first incomplete step
 * (SCREENS §2.5 F2). Published competitions go back to the overview.
 */
const ctx = useCompetitionContext()
const { t } = useI18n()
const auth = useAuthStore()
const appConfig = useAppConfigStore()
const localePath = useLocalePath()

const feesEnabled = computed(() => auth.features?.sponsorship_enabled === true && appConfig.sponsorshipEnabled)
let resolved = false

async function resolve(competition: IssuerCompetition): Promise<void> {
  if (resolved) return
  resolved = true
  const base = `/dashboard/competitions/${competition.id}`
  if (competition.status !== 'draft' || !competition.permissions.can_edit) {
    await navigateTo(localePath(base), { replace: true })
    return
  }
  let passesToBuy: number | null = null
  if (feesEnabled.value && competition.sponsorship) {
    try {
      passesToBuy = (await fetchSponsorshipQuote(competition.id)).passes_to_buy
    }
    catch {
      passesToBuy = null
    }
  }
  const step = firstIncompleteStep(draftProgressOf(competition, { feesEnabled: feesEnabled.value, quotePassesToBuy: passesToBuy }))
  await navigateTo(localePath(`${base}/setup/${step}`), { replace: true })
}

watch(() => ctx.competition.value, (current) => {
  if (current?.viewer_role === 'issuer') void resolve(current)
}, { immediate: true })
</script>

<template>
  <div
    class="flex flex-col gap-4"
    :aria-label="t('common.loading')"
  >
    <UiSkeleton class="h-16 w-full" />
    <UiSkeleton class="h-64 w-full" />
  </div>
</template>
