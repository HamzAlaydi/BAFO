<script setup lang="ts">
/**
 * Time left until `endsAt` on the server clock (SCREENS S3, CONVENTIONS §9.1; `useCountdown`).
 *
 * - ≥ 24 h: "N days HH:MM" with the Arabic 6-form plural; < 24 h: `HH:MM:SS`; tabular figures.
 * - Re-evaluated every 250 ms, repainted only when the displayed second changes.
 * - Warning tone in the last 5 minutes; never red, never flashing.
 * - `role="timer"`, not announced every second. With `announce`, 10 and 5 minutes are announced
 *   politely and 1 minute assertively.
 * - At zero it shows `endedLabel` (default «انتهى الوقت»). Live screens pass «جارٍ الإغلاق…»:
 *   "closed" comes only from the server (S3 step 9).
 * - SSR renders a placeholder so the server and client renders agree.
 */
const props = withDefaults(defineProps<{
  /** UTC ISO-8601 deadline. */
  endsAt: string | null | undefined
  /** Accessible context, e.g. «تُغلق خلال» / "Closes in". */
  label?: string
  /** Text at zero. */
  endedLabel?: string
  announce?: boolean
  size?: 'sm' | 'md' | 'lg'
}>(), {
  size: 'md',
})

const emit = defineEmits<{ expire: [], threshold: [minutes: number] }>()
const { t } = useI18n()
const politeMessage = ref('')
const assertiveMessage = ref('')

const { display, expired, warning } = useCountdown(() => props.endsAt, {
  onExpire: () => emit('expire'),
  onThreshold: (minutes) => {
    emit('threshold', minutes)
    if (!props.announce) return
    const message = t('common.countdown.threshold', { count: minutes }, minutes)
    if (minutes === 1) assertiveMessage.value = message
    else politeMessage.value = message
  },
})

const text = computed(() => {
  const value = display.value
  if (!value) return null
  if (value.mode === 'days') return `${t('common.countdown.days', { count: value.days }, value.days)} ${value.clock}`
  return value.clock
})

const accessibleText = computed(() => {
  if (!text.value) return undefined
  return props.label ? `${props.label} ${text.value}` : text.value
})

const sizeClasses = { sm: 'text-sm', md: 'text-base', lg: 'text-2xl' } as const
</script>

<template>
  <span class="inline-flex items-baseline gap-1.5">
    <span
      role="timer"
      aria-live="off"
      :aria-label="expired ? (endedLabel ?? t('common.countdown.ended')) : accessibleText"
      class="font-semibold whitespace-nowrap tabular-nums"
      :class="[sizeClasses[size], expired ? 'text-fg-muted' : warning ? 'text-warning-soft-fg' : 'text-fg']"
    >
      <span
        v-if="!text"
        aria-hidden="true"
      >--:--:--</span>
      <span v-else-if="expired">{{ endedLabel ?? t('common.countdown.ended') }}</span>
      <bdi v-else>{{ text }}</bdi>
    </span>
    <span
      v-if="announce"
      class="sr-only"
      aria-live="polite"
    >{{ politeMessage }}</span>
    <span
      v-if="announce"
      class="sr-only"
      aria-live="assertive"
    >{{ assertiveMessage }}</span>
  </span>
</template>
