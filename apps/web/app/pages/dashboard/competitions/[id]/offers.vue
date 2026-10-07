<script setup lang="ts">
import { fetchStandings } from '~/services/bidding'
import type { ParticipantStandingRow } from '~/types/api/bidding'
import type { IssuerCompetition } from '~/types/api/competitions'

/**
 * W20 Offers log (issuer) · `/dashboard/competitions/{id}/offers` (SCREENS §2.4): the accepted-offer
 * log with realtime appends, and a per-participant view from `GET …/offers` (current, first, change,
 * offers, contact, coverage). Export (offer log, CSV or XLSX) with `integrations.manage`.
 */
const ctx = useCompetitionContext()
const { t } = useI18n()
const auth = useAuthStore()
const features = useFeatures()

const competition = computed(() => (ctx.competition.value?.viewer_role === 'issuer' ? ctx.competition.value as IssuerCompetition : null))
useSeoMeta({ title: () => t('competitions.detail.tabs.offers') })

const view = ref<'log' | 'participants'>('log')
const viewOptions = computed(() => [
  { value: 'log' as const, label: t('offers.views.log') },
  { value: 'participants' as const, label: t('offers.views.participants') },
])

const standings = ref<ParticipantStandingRow[]>([])
const standingsLoading = ref(false)
const standingsError = ref<unknown>(null)
const standingsLoaded = ref(false)

async function loadStandings(): Promise<void> {
  const id = competition.value?.id
  if (!id) return
  standingsLoading.value = !standingsLoaded.value
  try {
    standings.value = await fetchStandings(id)
    standingsError.value = null
    standingsLoaded.value = true
  }
  catch (error) {
    standingsError.value = error
  }
  finally {
    standingsLoading.value = false
  }
}

const refreshStandings = useDebounceFn(() => loadStandings(), 1000)

watch(view, (value) => {
  if (value === 'participants' && !standingsLoaded.value) void loadStandings()
})

ctx.on('liveApplied', () => {
  if (standingsLoaded.value) void refreshStandings()
})

const viewModel = computed<'log' | 'participants' | null>({
  get: () => view.value,
  set: value => value && (view.value = value),
})
</script>

<template>
  <div
    v-if="competition"
    class="flex flex-col gap-5"
  >
    <UiAlert
      v-if="competition.status === 'draft'"
      tone="info"
    >
      {{ t('offers.log.draft') }}
    </UiAlert>
    <template v-else>
      <div class="flex flex-wrap items-center justify-between gap-3">
        <UiSegmented
          v-model="viewModel"
          :options="viewOptions"
          :label="t('offers.views.label')"
          size="sm"
        />
        <CompetitionsIssuerExportButton
          v-if="auth.can('integrations.manage') && features.enabled('csv_import_export')"
          :competition-id="competition.id"
          type="offer_log"
        />
      </div>
      <p
        v-if="competition.format === 'sealed' && !competition.schedule.offers_opened_at"
        class="text-sm text-fg-muted"
      >
        {{ t('offers.log.sealed_note') }}
      </p>

      <CompetitionsIssuerOfferLog
        v-if="view === 'log'"
        :competition-id="competition.id"
      />
      <template v-else>
        <UiCard
          v-if="standingsError && standings.length === 0"
          padding="none"
        >
          <UiErrorState
            :error="standingsError"
            @retry="loadStandings"
          />
        </UiCard>
        <CompetitionsIssuerStandingsTable
          v-else
          :rows="standings"
          :direction="competition.direction"
          :loading="standingsLoading"
        />
      </template>
      <p class="text-xs text-fg-muted">
        {{ t('common.prices_exclude_vat') }}
      </p>
    </template>
  </div>
</template>
