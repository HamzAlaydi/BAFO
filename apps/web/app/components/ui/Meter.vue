<script setup lang="ts">
/**
 * Seats and days-left meters (`bg-brand` fill on `bg-neutral-soft`). A native `<meter>` semantics
 * via `role="meter"`; the visible label and value text are the caller's.
 */
const props = withDefaults(defineProps<{
  value: number
  max: number
  label: string
  /** Human value text for assistive technology, e.g. «3 من 5 مقاعد». */
  valueText?: string
  /** Amber fill when high (seats nearly full) or low (days nearly over). */
  warnWhen?: 'high' | 'low' | 'never'
}>(), {
  warnWhen: 'never',
})

const ratio = computed(() => (props.max > 0 ? Math.min(1, Math.max(0, props.value / props.max)) : 0))
const warning = computed(() => {
  if (props.warnWhen === 'high') return ratio.value >= 1
  if (props.warnWhen === 'low') return props.max > 0 && ratio.value <= 0.2
  return false
})
</script>

<template>
  <div
    role="meter"
    :aria-label="label"
    :aria-valuenow="value"
    aria-valuemin="0"
    :aria-valuemax="max"
    :aria-valuetext="valueText"
    class="h-2 w-full overflow-hidden rounded-full bg-neutral-soft"
  >
    <div
      class="h-full rounded-full transition-[width] duration-300"
      :class="warning ? 'bg-warning' : 'bg-brand'"
      :style="{ width: `${ratio * 100}%` }"
    />
  </div>
</template>
