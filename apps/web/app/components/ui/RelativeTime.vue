<script setup lang="ts">
/**
 * Relative time for lists (< 7 days): «منذ 5 دقائق» / "5 minutes ago"; older items show the date
 * (CONVENTIONS §9.1). Measured on the server clock; refreshed every minute. The `title` carries the
 * full Riyadh date and time.
 */
const props = defineProps<{ value: string }>()

const { t } = useI18n()
const date = useDate()
const clock = useServerTime()
const nowMs = ref(clock.now())

useIntervalFn(() => {
  nowMs.value = clock.now()
}, 60_000)

const text = computed(() => {
  const relative = relativeTime(props.value, nowMs.value)
  switch (relative.unit) {
    case 'now': return t('common.relative.now')
    case 'minutes': return t('common.relative.minutes', { count: relative.value }, relative.value)
    case 'hours': return t('common.relative.hours', { count: relative.value }, relative.value)
    case 'days': return t('common.relative.days', { count: relative.value }, relative.value)
    default: return Number.isNaN(Date.parse(props.value)) ? '—' : date.formatDate(props.value)
  }
})

const title = computed(() => (Number.isNaN(Date.parse(props.value)) ? undefined : date.formatDeadline(props.value)))
</script>

<template>
  <time
    :datetime="value"
    :title="title"
    class="whitespace-nowrap"
  >{{ text }}</time>
</template>
