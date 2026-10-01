<script setup lang="ts">
import type { Component } from 'vue'
import { CircleAlert, CircleCheck, Info, ListOrdered } from '@lucide/vue'
import type { ParticipantLiveSnapshot } from '~/types/api/bidding'

/**
 * `StandingBanner` (SCREENS S2, W19): the participant's standing, straight from the projected
 * snapshot. Leading: primary-soft + check; not leading: warning-soft (amber, never red) + alert
 * and the direction hint; rank and hidden: neutral. The client never computes a rank or a leader.
 *
 * Standing changes are announced politely, at most once every 10 s (S9); the visible text changes
 * at once, with a short highlight that is off under reduced motion.
 */
const props = withDefaults(defineProps<{
  snapshot: ParticipantLiveSnapshot
  /** Compact variant for the overview. */
  compact?: boolean
  /** Announce changes through the live region (the live room); off where the room is not shown. */
  announce?: boolean
}>(), {
  compact: false,
  announce: true,
})

const { t } = useI18n()
const { td } = useDirectionCopy(() => props.snapshot.direction)

const standing = computed(() => standingOf(props.snapshot))

interface View {
  tone: 'leading' | 'outbid' | 'neutral'
  icon: Component
  title: string
  detail: string | null
}

function rankText(rank: number | null, count: number | null): string | null {
  if (rank === null) return null
  return count === null ? t('live.status.rank_only', { rank }) : t('live.status.rank', { rank, count })
}

const view = computed<View | null>(() => {
  const current = standing.value
  switch (current.kind) {
    case 'leading':
      return { tone: 'leading', icon: CircleCheck, title: t('live.status.leading'), detail: rankText(current.rank, current.rankedCount) }
    case 'not_leading':
      return { tone: 'outbid', icon: CircleAlert, title: td('live.status.not_leading'), detail: rankText(current.rank, current.rankedCount) }
    case 'ranked':
      return { tone: 'neutral', icon: ListOrdered, title: rankText(current.rank, current.rankedCount) ?? '', detail: null }
    case 'hidden':
      return { tone: 'neutral', icon: Info, title: t('live.status.hidden'), detail: null }
    case 'own_only':
      return { tone: 'neutral', icon: Info, title: t('live.status.own_only'), detail: null }
    case 'no_offer':
      return { tone: 'neutral', icon: Info, title: t('live.status.no_offer'), detail: null }
    default:
      // Sealed and BAFO have their own panels.
      return null
  }
})

const toneClasses = {
  leading: 'border-primary/25 bg-primary-soft text-primary-soft-fg',
  outbid: 'border-warning/40 bg-warning-soft text-warning-soft-fg',
  neutral: 'border-line bg-neutral-soft text-neutral-soft-fg',
} as const

const message = computed(() => (view.value ? [view.value.title, view.value.detail].filter(Boolean).join('. ') : ''))

// Polite announcements, throttled to one per 10 s (the latest text wins).
const announcer = useThrottledAnnouncer()

watch(message, (text, previous) => {
  if (!props.announce || !text || text === previous || previous === undefined) return
  announcer.announce(() => message.value)
})

// A short highlight when the standing changes (motion-safe only).
const highlight = ref(false)
watch(() => view.value?.tone, (tone, previous) => {
  if (!tone || !previous || tone === previous) return
  highlight.value = true
  setTimeout(() => {
    highlight.value = false
  }, 300)
})
</script>

<template>
  <div v-if="view">
    <div
      class="flex items-start gap-3 rounded-lg border transition-shadow duration-300"
      :class="[toneClasses[view.tone], compact ? 'p-3' : 'p-4', highlight && 'motion-safe:ring-2 motion-safe:ring-ring/60']"
      data-testid="standing-banner"
      :data-tone="view.tone"
    >
      <component
        :is="view.icon"
        :size="compact ? 20 : 24"
        class="mt-0.5 shrink-0"
        aria-hidden="true"
      />
      <div class="min-w-0">
        <p
          class="font-bold"
          :class="compact ? 'text-sm' : 'text-base'"
        >
          {{ view.title }}
        </p>
        <p
          v-if="view.detail"
          class="mt-0.5 text-sm"
        >
          {{ view.detail }}
        </p>
      </div>
    </div>
    <p
      v-if="announce"
      class="sr-only"
      aria-live="polite"
      aria-atomic="true"
    >
      {{ announcer.message.value }}
    </p>
  </div>
</template>
