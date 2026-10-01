<script setup lang="ts">
import type { IssuerLiveSnapshot } from '~/types/api/bidding'
import type { IssuerCompetition } from '~/types/api/competitions'

/**
 * Issuer part of the W13 header (SCREENS S3): the server-time countdown to the current target
 * (scheduled → opens; live → effective close; BAFO round → cutoff), the latest possible close under
 * auto-extend, and the extension count. At zero it reads «جارٍ الإغلاق…»: "closed" only comes from
 * the server (S3 step 9).
 */
const props = defineProps<{ live: IssuerLiveSnapshot | null }>()

const ctx = useCompetitionContext()
const { t } = useI18n()

const competition = computed(() => (ctx.competition.value?.viewer_role === 'issuer' ? ctx.competition.value as IssuerCompetition : null))
const status = computed(() => props.live?.status ?? competition.value?.status ?? null)

const countdown = computed<{ target: string, label: string, ended: string } | null>(() => {
  const current = competition.value
  if (!current) return null
  if (status.value === 'scheduled' && current.schedule.bidding_opens_at) {
    return { target: current.schedule.bidding_opens_at, label: t('competitions.detail.countdown.opens'), ended: t('competitions.detail.countdown.opening') }
  }
  if (status.value === 'live') {
    const target = props.live?.effective_close_at ?? current.schedule.effective_close_at
    return target ? { target, label: t('competitions.detail.countdown.closes'), ended: t('competitions.detail.countdown.closing') } : null
  }
  if (status.value === 'bafo_round') {
    const target = props.live?.bafo?.cutoff_at ?? current.bafo_round?.cutoff_at
    return target ? { target, label: t('competitions.detail.countdown.bafo_closes'), ended: t('competitions.detail.countdown.closing') } : null
  }
  return null
})

const hardStop = computed(() => (status.value === 'live' ? props.live?.hard_stop_at ?? competition.value?.schedule.hard_stop_at ?? null : null))
const extensions = computed(() => props.live?.extension_count ?? competition.value?.schedule.extension_count ?? 0)
</script>

<template>
  <div
    v-if="countdown || hardStop"
    class="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm"
  >
    <span
      v-if="countdown"
      class="inline-flex flex-wrap items-baseline gap-1.5"
    >
      <span class="text-fg-muted">{{ countdown.label }}</span>
      <UiCountdown
        :ends-at="countdown.target"
        :label="countdown.label"
        :ended-label="countdown.ended"
        size="md"
        announce
      />
    </span>
    <span
      v-if="hardStop"
      class="inline-flex flex-wrap items-baseline gap-1.5"
    >
      <span class="text-fg-muted">{{ t('competitions.detail.countdown.latest_close') }}</span>
      <UiDateTime
        :value="hardStop"
        format="deadline"
        class="font-semibold text-fg"
      />
    </span>
    <span
      v-if="extensions > 0"
      class="text-fg-muted"
    >
      {{ t('competitions.detail.countdown.extensions', { count: extensions }, extensions) }}
    </span>
  </div>
</template>
