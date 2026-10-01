<script setup lang="ts">
/**
 * Server-localised text that embeds codes (SCREENS S1 LTR islands): reference numbers such as
 * `BAFO-T-2026-000123`, voucher codes and invoice numbers are wrapped in `<bdi dir="ltr">` and kept
 * on one line, so an Arabic sentence never reorders or splits them.
 */
const props = defineProps<{ text: string | null | undefined }>()

const CODE = /[A-Za-z0-9]+(?:[-_./][A-Za-z0-9]+)+/g

const parts = computed(() => {
  const text = props.text ?? ''
  const result: Array<{ code: boolean, value: string }> = []
  let last = 0
  for (const match of text.matchAll(CODE)) {
    const index = match.index ?? 0
    if (index > last) result.push({ code: false, value: text.slice(last, index) })
    result.push({ code: true, value: match[0] })
    last = index + match[0].length
  }
  if (last < text.length) result.push({ code: false, value: text.slice(last) })
  return result
})
</script>

<template>
  <span><template
    v-for="(part, index) in parts"
    :key="index"
  ><bdi
    v-if="part.code"
    dir="ltr"
    class="whitespace-nowrap"
  >{{ part.value }}</bdi><template v-else>{{ part.value }}</template></template></span>
</template>
