<script setup lang="ts">
import { CircleCheck, CircleDashed } from '@lucide/vue'
import type { IssuerCompetition } from '~/types/api/competitions'

/**
 * Published schedule of a competition (SCREENS W14 `ScheduleTimeline`): published, opens, final
 * window start, invitation cutoff, scheduled close, effective close (after extensions), latest
 * possible close, closed, offers opened, BAFO round, awarded, closed without award, cancelled.
 * Past events are checked; upcoming ones use a dashed marker. Times are Riyadh time.
 */
const props = defineProps<{ competition: IssuerCompetition, effectiveCloseAt?: string | null, serverNowMs: number }>()
const { t } = useI18n()

const items = computed(() => {
  const s = props.competition.schedule
  const effective = props.effectiveCloseAt ?? s.effective_close_at
  const round = props.competition.bafo_round
  const rows: Array<{ key: string, at: string | null }> = [
    { key: 'published', at: s.published_at },
    { key: 'opens', at: s.opened_at ?? s.bidding_opens_at },
    { key: 'final_window', at: s.final_window_starts_at },
    { key: 'invitation_cutoff', at: s.invitation_cutoff_at },
    { key: 'scheduled_close', at: s.scheduled_close_at },
  ]
  if (effective && effective !== s.scheduled_close_at) rows.push({ key: 'effective_close', at: effective })
  rows.push({ key: 'hard_stop', at: s.hard_stop_at })
  rows.push({ key: 'closed', at: s.closed_at })
  if (props.competition.format === 'sealed') rows.push({ key: 'offers_opened', at: s.offers_opened_at })
  if (round) {
    rows.push({ key: 'bafo_started', at: round.starts_at })
    rows.push({ key: round.ended_at ? 'bafo_ended' : 'bafo_cutoff', at: round.ended_at ?? round.cutoff_at })
  }
  rows.push({ key: 'awarded', at: s.awarded_at }, { key: 'not_awarded', at: s.not_awarded_at }, { key: 'cancelled', at: s.cancelled_at })
  return rows.filter((row): row is { key: string, at: string } => Boolean(row.at))
})

const isPast = (at: string) => Date.parse(at) <= props.serverNowMs
</script>

<template>
  <ol class="flex flex-col">
    <li
      v-for="item in items"
      :key="item.key"
      class="flex gap-3 pb-3 last:pb-0"
    >
      <component
        :is="isPast(item.at) ? CircleCheck : CircleDashed"
        :size="18"
        class="mt-0.5 shrink-0"
        :class="isPast(item.at) ? 'text-brand' : 'text-fg-muted'"
        aria-hidden="true"
      />
      <span class="flex min-w-0 flex-col">
        <span class="text-sm text-fg-muted">{{ t(`competitions.issuer.timeline.${item.key}`) }}</span>
        <UiDateTime
          :value="item.at"
          class="text-sm font-semibold text-fg"
        />
      </span>
    </li>
    <li
      v-if="competition.schedule.extension_count > 0"
      class="text-sm text-fg-muted"
    >
      {{ t('competitions.detail.countdown.extensions', { count: competition.schedule.extension_count }, competition.schedule.extension_count) }}
    </li>
  </ol>
</template>
