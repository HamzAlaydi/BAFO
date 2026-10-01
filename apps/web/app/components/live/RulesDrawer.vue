<script setup lang="ts">
import { ExternalLink } from '@lucide/vue'

/**
 * `RulesDrawer` (SCREENS W19): the server's rules summary for this participant (ARCHITECTURE
 * §7.16; the reserve price is never mentioned to participants) and the general competition terms.
 */
defineProps<{
  lines: string[]
}>()

const open = defineModel<boolean>('open', { default: false })
const { t } = useI18n()
const localePath = useLocalePath()
</script>

<template>
  <UiDrawer
    v-model:open="open"
    :title="t('live.room.rules_title')"
    size="md"
  >
    <div class="flex flex-col gap-5 p-5">
      <CompetitionsRulesSummary :lines="lines" />
      <a
        :href="localePath('/legal/competition_rules')"
        target="_blank"
        rel="noopener"
        class="link inline-flex items-center gap-1.5 text-sm font-semibold"
      >
        {{ t('live.room.rules_legal') }}
        <ExternalLink
          :size="14"
          aria-hidden="true"
        />
        <span class="sr-only">{{ t('competitions.participant.new_tab') }}</span>
      </a>
    </div>
  </UiDrawer>
</template>
