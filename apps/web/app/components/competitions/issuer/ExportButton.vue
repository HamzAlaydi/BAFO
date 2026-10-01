<script setup lang="ts">
import { FileDown } from '@lucide/vue'
import { createExportJob, fetchExportJob } from '~/services/integrations'
import type { ExportFormat, ExportJob } from '~/types/api/integrations'

/**
 * Offer-log or results export for one competition (SCREENS W20, W22; `integrations.manage`):
 * `POST /integrations/exports` → poll the job every 2 s → download the private file with the bearer
 * header. The caller hides it without the permission.
 */
const props = defineProps<{ competitionId: string, type: 'offer_log' | 'results' }>()

const { t } = useI18n()
const toast = useToast()
const { message } = useErrorMessage()
const { download, downloading } = useFileDownload()

const starting = ref(false)
const jobId = ref<string | null>(null)
const job = useJobPoll<ExportJob>(() => fetchExportJob(jobId.value ?? ''))

const busy = computed(() => starting.value || job.polling.value || downloading.value)
const items = computed(() => (['xlsx', 'csv'] as const).map(format => ({ key: format, label: t(`competitions.issuer.export.${format}`) })))

async function start(format: string): Promise<void> {
  starting.value = true
  try {
    const created = await createExportJob({ type: props.type, format: format as ExportFormat, competition_id: props.competitionId })
    jobId.value = created.id
    job.start(created)
  }
  catch (error) {
    toast.error(message(error))
  }
  finally {
    starting.value = false
  }
}

watch(() => job.data.value, async (value) => {
  if (!value || value.id !== jobId.value) return
  if (value.status === 'failed') {
    toast.error(value.failure_message || t('competitions.issuer.export.failed'))
    jobId.value = null
  }
  else if (value.status === 'completed' && value.file) {
    jobId.value = null
    try {
      await download(value.file.download_path, value.file.name)
    }
    catch (error) {
      toast.error(message(error))
    }
  }
})

watch(() => job.error.value, (error) => {
  if (error) toast.error(message(error))
})
</script>

<template>
  <UiDropdownMenu
    :label="t(`competitions.issuer.export.${type}`)"
    :items="items"
    trigger-class="h-9 gap-1.5 rounded-md border border-line bg-surface px-3 text-sm font-bold text-fg shadow-xs hover:bg-surface-muted"
    @select="start"
  >
    <template #trigger>
      <UiSkeleton
        v-if="busy"
        shape="circle"
        class="size-4"
      />
      <FileDown
        v-else
        :size="16"
        aria-hidden="true"
      />
      {{ busy ? t('competitions.issuer.export.preparing') : t(`competitions.issuer.export.${type}`) }}
    </template>
  </UiDropdownMenu>
</template>
