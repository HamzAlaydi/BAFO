<script setup lang="ts">
/** Monospace, LTR, scrollable pane for JSON or response excerpts (webhook deliveries). */
const props = defineProps<{
  value: unknown
  label: string
}>()

const text = computed(() => {
  if (typeof props.value === 'string') {
    try {
      return JSON.stringify(JSON.parse(props.value), null, 2)
    }
    catch {
      return props.value
    }
  }
  return JSON.stringify(props.value, null, 2) ?? ''
})
</script>

<template>
  <pre
    dir="ltr"
    tabindex="0"
    :aria-label="label"
    class="max-h-80 overflow-auto rounded-md border border-line bg-surface-muted p-3 text-start font-mono text-xs leading-relaxed text-fg focus-visible:outline-2 focus-visible:outline-ring"
  >{{ text }}</pre>
</template>
