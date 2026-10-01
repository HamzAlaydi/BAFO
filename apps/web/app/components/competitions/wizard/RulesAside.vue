<script setup lang="ts">
import { Check } from '@lucide/vue'
import { editorRulesPreview } from '~/stores/competition-editor-rules'

/**
 * The wizard's side "Rules summary" (SCREENS W15 chrome). Saved rules show the server's
 * `rules_summary` (the reference text, ARCHITECTURE §7.16). While the rules have unsaved changes, or
 * before the draft exists, a client preview in plain words is shown instead, labelled as such, so the
 * issuer sees the effect of every control at once.
 */
const props = defineProps<{ serverLines: string[] | null }>()

const { t } = useI18n()
const editor = useCompetitionEditorStore()
const money = useMoney()

const previewing = computed(() => props.serverLines === null || editor.isDirty('rules') || editor.isDirty('type'))

const lines = computed(() => {
  if (!previewing.value) return props.serverLines ?? []
  return editorRulesPreview(editor.form.rules, editor.form.direction, editor.form.format).map((line) => {
    const amounts = Object.fromEntries(Object.entries(line.amounts ?? {}).map(([key, minor]) => [key, money.format(minor)]))
    return t(line.key, { ...line.params, ...amounts })
  })
})
</script>

<template>
  <section
    class="flex flex-col gap-3"
    :aria-label="t('rules.preview.title')"
  >
    <div class="flex flex-wrap items-center justify-between gap-2">
      <h2 class="text-base font-bold text-fg">
        {{ t('rules.preview.title') }}
      </h2>
      <UiBadge
        v-if="previewing"
        tone="neutral"
        size="sm"
      >
        {{ t('rules.preview.unsaved') }}
      </UiBadge>
    </div>
    <ul class="flex flex-col gap-2 text-sm text-fg">
      <li
        v-for="(line, index) in lines"
        :key="index"
        class="flex items-start gap-2"
      >
        <Check
          :size="16"
          class="mt-0.5 shrink-0 text-brand"
          aria-hidden="true"
        />
        <span>{{ line }}</span>
      </li>
    </ul>
  </section>
</template>
