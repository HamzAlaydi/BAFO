<script setup lang="ts">
import { FileText } from '@lucide/vue'
import { fetchReport } from '~/services/bidding'
import type { AppLocale } from '~/types/api/common'
import type { Report } from '~/types/api/bidding'

/**
 * Result report PDF (SCREENS W22 `ReportButton`, ARCHITECTURE §7.14): choose Arabic or English, then
 * `GET …/report?locale=`: 200 `ready` downloads the private file with the bearer header (streamed as a
 * blob, never a public URL); 202 `pending` polls every 3 s; `report_not_available` hides the control.
 */
const props = defineProps<{ competitionId: string }>()

const { t } = useI18n()
const appLocale = useAppLocale()
const toast = useToast()
const { message } = useErrorMessage()
const { download, downloading } = useFileDownload()

/** Stop polling after 2 minutes; the issuer can ask again (the server keeps generating). */
const POLL_LIMIT_MS = 120_000

const locale = ref<AppLocale>(appLocale.value)
const requesting = ref(false)
const unavailable = ref(false)
const failed = ref(false)
const slow = ref(false)
let pollLimit: ReturnType<typeof setTimeout> | null = null
const job = useJobPoll<Report>(() => fetchReport(props.competitionId, locale.value), { intervalMs: 3000 })

const busy = computed(() => requesting.value || job.polling.value || downloading.value)
const localeOptions = computed(() => [
  { value: 'ar' as const, label: t('common.languages.ar') },
  { value: 'en' as const, label: t('common.languages.en') },
])
const localeModel = computed<AppLocale | null>({
  get: () => locale.value,
  set: value => value && (locale.value = value),
})

async function save(report: Report): Promise<void> {
  if (!report.file) return
  try {
    await download(report.file.download_path, report.file.name)
  }
  catch (error) {
    toast.error(message(error))
  }
}

async function request(): Promise<void> {
  requesting.value = true
  failed.value = false
  slow.value = false
  try {
    const report = await fetchReport(props.competitionId, locale.value)
    if (report.status === 'ready') await save(report)
    else if (report.status === 'failed') failed.value = true
    else {
      job.start(report)
      if (pollLimit) clearTimeout(pollLimit)
      pollLimit = setTimeout(() => {
        if (!job.polling.value) return
        job.stop()
        slow.value = true
      }, POLL_LIMIT_MS)
    }
  }
  catch (error) {
    if (error instanceof ApiError && error.code === 'report_not_available') unavailable.value = true
    else toast.error(message(error))
  }
  finally {
    requesting.value = false
  }
}

onBeforeUnmount(() => {
  if (pollLimit) clearTimeout(pollLimit)
})

watch(() => job.data.value, (report) => {
  if (!report || job.polling.value) return
  if (report.status === 'ready') void save(report)
  else if (report.status === 'failed') failed.value = true
})
</script>

<template>
  <UiCard
    v-if="!unavailable"
    :title="t('award.report.title')"
    :description="t('award.report.description')"
    padding="sm"
  >
    <div class="flex flex-wrap items-end gap-3">
      <UiSegmented
        v-model="localeModel"
        :options="localeOptions"
        :label="t('award.report.language')"
        size="sm"
      />
      <UiButton
        variant="secondary"
        :icon="FileText"
        :loading="busy"
        @click="request"
      >
        {{ job.polling.value ? t('award.report.preparing') : t('award.report.download') }}
      </UiButton>
    </div>
    <p
      v-if="failed"
      class="mt-3 text-sm text-danger"
      role="alert"
    >
      {{ t('award.report.failed') }}
    </p>
    <p
      v-else-if="slow"
      class="mt-3 text-sm text-fg-muted"
      role="status"
    >
      {{ t('award.report.slow') }}
    </p>
  </UiCard>
</template>
