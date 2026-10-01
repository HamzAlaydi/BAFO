<script setup lang="ts">
import type { Component } from 'vue'
import { Ban, CalendarClock, CircleDot, CircleSlash, ClipboardCheck, Lock, PencilLine, Timer, Trophy } from '@lucide/vue'
import type { CompetitionPhase, CompetitionStatus } from '~/types/api/competitions'
import type { StatusIcon, StatusTone } from '~/utils/competition-status'
import type { Tone } from '~/types/ui'

/**
 * Competition status chip (SCREENS S2), driven only by `competitionStatusVisual()` on the server
 * clock. While `live` it adds the overlay pills "Closing soon" (warning) and "Extended" (info).
 * Colour is never the only signal: every chip has an icon and text. Red only for `cancelled`.
 */
const props = withDefaults(defineProps<{
  status: CompetitionStatus
  phase?: CompetitionPhase | null
  effectiveCloseAt?: string | null
  extensionCount?: number | null
  /** Render the overlay pills next to the chip (default true). */
  overlays?: boolean
  size?: 'sm' | 'md'
}>(), {
  phase: null,
  effectiveCloseAt: null,
  extensionCount: 0,
  overlays: true,
  size: 'md',
})

const { t } = useI18n()
const clock = useServerTime()
const nowMs = ref(clock.now())

// "Closing soon" appears as time passes; a coarse tick is enough for a label.
const { pause, resume } = useIntervalFn(() => {
  nowMs.value = clock.now()
}, 15_000, { immediate: false })

watch(() => props.status, (status) => {
  if (status === 'live') resume()
  else pause()
}, { immediate: true })

const visual = computed(() => competitionStatusVisual(props.status, props.phase, props.effectiveCloseAt, props.extensionCount, nowMs.value))

const ICONS: Record<Exclude<StatusIcon, 'brand_mark'>, Component> = {
  pencil: PencilLine,
  calendar_clock: CalendarClock,
  circle_dot: CircleDot,
  timer: Timer,
  lock: Lock,
  clipboard_check: ClipboardCheck,
  trophy: Trophy,
  circle_slash: CircleSlash,
  ban: Ban,
}

const TONES: Record<StatusTone, { tone: Tone, solid: boolean }> = {
  neutral: { tone: 'neutral', solid: false },
  info: { tone: 'info', solid: false },
  primary_soft: { tone: 'primary', solid: false },
  primary: { tone: 'primary', solid: true },
  inverse: { tone: 'neutral', solid: true },
  danger_soft: { tone: 'danger', solid: false },
}

const badge = computed(() => TONES[visual.value.tone])
const icon = computed(() => (visual.value.icon === 'brand_mark' ? undefined : ICONS[visual.value.icon]))
</script>

<template>
  <span class="inline-flex flex-wrap items-center gap-1.5">
    <UiBadge
      :tone="badge.tone"
      :solid="badge.solid"
      :icon="icon"
      :pulse="visual.pulse"
      :size="size"
    >
      <span class="inline-flex items-center gap-1.5">
        <AppBrandMark
          v-if="visual.icon === 'brand_mark'"
          :size-class="size === 'sm' ? 'size-3' : 'size-3.5'"
        />
        {{ t(`competitions.status.${visual.key}`) }}
      </span>
    </UiBadge>
    <template v-if="overlays">
      <UiBadge
        v-if="visual.overlays.includes('closing_soon')"
        tone="warning"
        :icon="Timer"
        :size="size"
      >
        {{ t('competitions.status.closing_soon') }}
      </UiBadge>
      <UiBadge
        v-if="visual.overlays.includes('extended')"
        tone="info"
        :icon="CalendarClock"
        :size="size"
      >
        {{ t('competitions.status.extended') }}
      </UiBadge>
    </template>
  </span>
</template>
