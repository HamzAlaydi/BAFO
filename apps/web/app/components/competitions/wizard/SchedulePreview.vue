<script setup lang="ts">
import type { Rules } from '~/types/api/competitions'
import { editorSchedulePreview } from '~/stores/competition-editor-schedule'

/**
 * Draft timeline preview (SCREENS W15 step 4, §6 G3): opens → final window start → invitation cutoff →
 * close → latest close. Drafts have no derived times yet, so the values are estimated with the
 * ARCHITECTURE §7.2 formulas and labelled «تقديري، يُثبَّت عند النشر».
 */
const props = defineProps<{
  biddingOpensAt: string | null
  scheduledCloseAt: string | null
  rules: Pick<Rules, 'final_window_minutes' | 'auto_extend'>
  nowMs: number
}>()

const { t } = useI18n()

const preview = computed(() => editorSchedulePreview({
  biddingOpensAt: props.biddingOpensAt,
  scheduledCloseAt: props.scheduledCloseAt,
  rules: props.rules,
  nowMs: props.nowMs,
}))

const iso = (ms: number | null) => (ms === null ? null : new Date(ms).toISOString())

const items = computed(() => {
  const p = preview.value
  const rows: Array<{ key: string, label: string, value: string | null, note?: string }> = [
    { key: 'opens', label: t('competitions.setup.schedule.timeline.opens'), value: p.opensOnPublish ? null : iso(p.opensAtMs), note: p.opensOnPublish ? t('competitions.setup.schedule.opens_on_publish') : undefined },
  ]
  if (p.finalWindowStartsAtMs !== null) rows.push({ key: 'final_window', label: t('competitions.setup.schedule.timeline.final_window'), value: iso(p.finalWindowStartsAtMs) })
  if (p.invitationCutoffAtMs !== null) rows.push({ key: 'cutoff', label: t('competitions.setup.schedule.timeline.invitation_cutoff'), value: iso(p.invitationCutoffAtMs), note: t('competitions.setup.schedule.timeline.invitation_cutoff_note') })
  rows.push({ key: 'close', label: t('competitions.setup.schedule.timeline.close'), value: iso(p.closeAtMs), note: p.closeAtMs === null ? t('competitions.setup.schedule.not_set') : undefined })
  if (p.hardStopAtMs !== null) rows.push({ key: 'hard_stop', label: t('competitions.setup.schedule.timeline.latest_close'), value: iso(p.hardStopAtMs) })
  return rows
})

const duration = computed(() => {
  const ms = preview.value.durationMs
  if (ms === null || ms <= 0) return null
  const display = countdownDisplay(ms)
  return display.mode === 'days' ? `${t('common.countdown.days', { count: display.days }, display.days)} ${display.clock}` : display.clock
})
</script>

<template>
  <section class="flex flex-col gap-3">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <h3 class="text-base font-bold text-fg">
        {{ t('competitions.setup.schedule.timeline.title') }}
      </h3>
      <UiBadge
        tone="neutral"
        size="sm"
      >
        {{ t('competitions.setup.schedule.estimated') }}
      </UiBadge>
    </div>
    <ol class="flex flex-col">
      <li
        v-for="(item, index) in items"
        :key="item.key"
        class="relative flex gap-3 pb-4 last:pb-0"
      >
        <span
          class="relative z-10 mt-1.5 size-2.5 shrink-0 rounded-full bg-brand"
          aria-hidden="true"
        />
        <span
          v-if="index < items.length - 1"
          class="absolute start-[0.28rem] top-4 bottom-0 w-px bg-line"
          aria-hidden="true"
        />
        <span class="flex min-w-0 flex-col">
          <span class="text-sm text-fg-muted">{{ item.label }}</span>
          <UiDateTime
            v-if="item.value"
            :value="item.value"
            class="font-semibold text-fg"
          />
          <span
            v-else
            class="font-semibold text-fg"
          >{{ item.note }}</span>
          <span
            v-if="item.value && item.note"
            class="text-xs text-fg-muted"
          >{{ item.note }}</span>
        </span>
      </li>
    </ol>
    <p
      v-if="duration"
      class="text-sm text-fg-muted"
    >
      {{ t('competitions.setup.schedule.duration') }}
      <bdi class="font-semibold text-fg tabular-nums">{{ duration }}</bdi>
    </p>
  </section>
</template>
