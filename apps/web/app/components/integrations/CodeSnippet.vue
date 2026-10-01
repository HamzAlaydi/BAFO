<script setup lang="ts">
/** A copyable code sample (LTR, monospace, scrolls sideways inside its own box). Placeholders only. */
defineProps<{
  label: string
  code: string
}>()

const { t } = useI18n()
const id = useId()
</script>

<template>
  <figure class="flex min-w-0 flex-col gap-1.5">
    <figcaption
      :id="id"
      class="flex items-center justify-between gap-2 text-sm font-semibold text-fg"
    >
      {{ label }}
      <UiCopyButton
        :value="code"
        :label="t('integrations.common.copy_snippet', { label })"
      />
    </figcaption>
    <pre
      dir="ltr"
      tabindex="0"
      :aria-labelledby="id"
      class="max-w-full overflow-x-auto rounded-md border border-line bg-surface-muted p-3 text-start font-mono text-xs leading-relaxed text-fg focus-visible:outline-2 focus-visible:outline-ring"
    ><code>{{ code }}</code></pre>
  </figure>
</template>
