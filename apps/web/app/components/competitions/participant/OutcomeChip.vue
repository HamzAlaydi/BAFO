<script setup lang="ts">
import { CircleSlash, Trophy, UserRoundX } from '@lucide/vue'
import type { ResultOutcome } from '~/types/api/competitions'

/**
 * Result chip for participants (SCREENS S2 `award.outcome.*`): «تمت الترسية عليكم» (primary solid,
 * trophy), «لم يتم اختياركم» and «أُغلقت دون ترسية» (neutral). Icon and text, never colour alone.
 */
const props = withDefaults(defineProps<{
  outcome: ResultOutcome
  size?: 'sm' | 'md'
}>(), {
  size: 'md',
})

const { t } = useI18n()
const icon = computed(() => ({ won: Trophy, not_selected: UserRoundX, not_awarded: CircleSlash })[props.outcome])
</script>

<template>
  <UiBadge
    :tone="outcome === 'won' ? 'primary' : 'neutral'"
    :solid="outcome === 'won'"
    :icon="icon"
    :size="size"
  >
    {{ t(`award.outcome.${outcome}`) }}
  </UiBadge>
</template>
