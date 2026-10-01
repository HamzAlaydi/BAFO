<script setup lang="ts">
import type { JobStatus } from '~/types/api/integrations'

/**
 * `JobProgress` (SCREENS W41, W42): an import or export job. The API reports a status, not a
 * percentage, so the bar is indeterminate while `queued` or `processing`. Status changes are
 * announced politely.
 */
defineProps<{
  status: JobStatus
  /** What the job does, e.g. «نتحقق من الملف…». */
  label: string
}>()

const { t } = useI18n()
</script>

<template>
  <div
    class="flex flex-col gap-2"
    role="status"
    aria-live="polite"
  >
    <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
      <span class="font-semibold text-fg">{{ label }}</span>
      <UiBadge
        :tone="JOB_STATUS_TONES[status] ?? 'neutral'"
        size="sm"
        dot
      >
        {{ t(`integrations.jobs.statuses.${status}`) }}
      </UiBadge>
    </div>
    <UiProgress
      v-if="status === 'queued' || status === 'processing'"
      :label="label"
    />
  </div>
</template>
