<script setup lang="ts">
/**
 * A date or time in Asia/Riyadh, Gregorian, Western digits (SCREENS S6): «9 نوفمبر 2026، 12:59 م» /
 * "9 Nov 2026, 12:59 PM". `deadline` appends «بتوقيت الرياض» / "Riyadh time". The `title` always
 * carries the full date and time with the zone.
 */
const props = withDefaults(defineProps<{
  value: string | null | undefined
  format?: 'date' | 'time' | 'datetime' | 'deadline'
  empty?: string
}>(), {
  format: 'datetime',
  empty: '—',
})

const date = useDate()

const valid = computed(() => Boolean(props.value) && !Number.isNaN(Date.parse(props.value ?? '')))

const text = computed(() => {
  if (!valid.value || !props.value) return null
  switch (props.format) {
    case 'date': return date.formatDate(props.value)
    case 'time': return date.formatTime(props.value)
    case 'deadline': return date.formatDeadline(props.value)
    default: return date.formatDateTime(props.value)
  }
})

const title = computed(() => (valid.value && props.value ? date.formatDeadline(props.value) : undefined))
</script>

<template>
  <time
    v-if="text"
    :datetime="value ?? undefined"
    :title="title"
    class="whitespace-nowrap"
  >{{ text }}</time>
  <span v-else>{{ empty }}</span>
</template>
