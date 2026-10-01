<script setup lang="ts">
import type { TeamSeats } from '~/types/api/identity'

/** «3 من 5 مقاعد» with a meter that turns amber when every seat is used (SCREENS W26). */
const props = defineProps<{ seats: TeamSeats }>()
const { t } = useI18n()

const text = computed(() => t('team.seats.used', { used: props.seats.used, total: props.seats.total }))
</script>

<template>
  <div class="flex flex-col gap-2">
    <div class="flex items-center justify-between gap-3 text-sm">
      <span class="font-semibold text-fg">{{ t('team.seats.title') }}</span>
      <span class="text-fg-muted tabular-nums">{{ text }}</span>
    </div>
    <UiMeter
      :value="seats.used"
      :max="seats.total"
      :label="t('team.seats.title')"
      :value-text="text"
      warn-when="high"
    />
  </div>
</template>
