<script setup lang="ts">
import { CircleAlert, CircleCheck } from '@lucide/vue'
import type { Direction } from '~/types/api/competitions'
import type { ChecklistItem } from '~/stores/competition-editor-steps'

/**
 * Draft checklist (SCREENS W14 draft, W15 step 8): each item complete or missing, linking to its
 * wizard step. Hints only: the server runs the publish checks.
 */
const props = defineProps<{ items: ChecklistItem[], competitionId: string, direction: Direction }>()
const { t } = useI18n()

const missing = computed(() => props.items.filter(item => !item.complete).length)
</script>

<template>
  <div class="flex flex-col gap-3">
    <p
      class="text-sm"
      :class="missing > 0 ? 'text-fg' : 'text-success-soft-fg'"
      role="status"
    >
      {{ missing > 0 ? t('competitions.setup.checklist.missing', { count: missing }, missing) : t('competitions.setup.checklist.ready') }}
    </p>
    <ul class="flex flex-col gap-2">
      <li
        v-for="item in items"
        :key="item.key"
        class="flex items-start gap-2 text-sm"
      >
        <component
          :is="item.complete ? CircleCheck : CircleAlert"
          :size="18"
          class="mt-0.5 shrink-0"
          :class="item.complete ? 'text-brand' : 'text-warning-soft-fg'"
          aria-hidden="true"
        />
        <span class="flex min-w-0 flex-1 flex-wrap items-baseline justify-between gap-x-3">
          <span class="text-fg">
            {{ item.key === 'start_price' ? t(`competitions.setup.checklist.items.start_price.${direction}`) : t(`competitions.setup.checklist.items.${item.key}`) }}
            <span class="sr-only">{{ item.complete ? t('competitions.setup.checklist.done') : t('competitions.setup.checklist.todo') }}</span>
          </span>
          <NuxtLinkLocale
            v-if="!item.complete"
            :to="`/dashboard/competitions/${competitionId}/setup/${item.step}`"
            class="link text-sm"
          >
            {{ t('competitions.setup.checklist.fix') }}
          </NuxtLinkLocale>
        </span>
      </li>
    </ul>
  </div>
</template>
