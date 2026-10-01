<script setup lang="ts">
import { Download, FileDown } from '@lucide/vue'
import { createExportJob, fetchExportJob } from '~/services/integrations'
import type { CreateExportRequest, ExportJob, ExportType } from '~/types/api/integrations'
import type { TableColumn } from '~/types/ui'
import type { SessionExport } from '~/utils/integrations-display'

/**
 * W42 Exports · `/dashboard/integrations/exports?type=` (`integrations.manage`; SCREENS §2.4, §6 G2).
 * `POST /integrations/exports` → poll `GET /integrations/exports/{job}` every 2 s → download `file`.
 * There is no list endpoint, so the page lists the jobs started in this browser session
 * (`sessionStorage`, per-viewer convenience).
 */
definePageMeta({ layout: 'dashboard', middleware: 'auth' })

const props = withDefaults(defineProps<{ pollIntervalMs?: number }>(), { pollIntervalMs: 2000 })

const { t } = useI18n()
const auth = useAuthStore()
const route = useRoute()
const toast = useToast()
const files = useFileDownload()
const { message, bind } = useErrorMessage()

useSeoMeta({ title: () => t('integrations.exports.title') })

const allowed = computed(() => auth.can('integrations.manage'))
const EXPORT_TYPES: readonly ExportType[] = ['results', 'offer_log', 'awards', 'vendors']
const initialType = computed<ExportType>(() => {
  const value = queryString(route.query.type)
  return value && (EXPORT_TYPES as readonly string[]).includes(value) ? value as ExportType : 'results'
})

const entries = ref<SessionExport[]>([])
const creating = ref(false)
const formError = ref<string | null>(null)
const serverErrors = ref<Record<string, string>>({})
const downloadingId = ref<string | null>(null)
const pollErrors = ref<Record<string, string>>({})
let timer: ReturnType<typeof setTimeout> | null = null
let stopped = false

const isTerminal = (job: ExportJob) => job.status === 'completed' || job.status === 'failed'
const pending = computed(() => entries.value.filter(entry => !isTerminal(entry.job)))

function save(next: SessionExport[]): void {
  entries.value = next
  writeSessionExports(next)
}

async function refresh(ids: string[]): Promise<void> {
  const results = await Promise.allSettled(ids.map(id => fetchExportJob(id)))
  let next = entries.value
  const errors = new Map(Object.entries(pollErrors.value))
  results.forEach((result, index) => {
    const id = ids[index] ?? ''
    if (result.status === 'fulfilled') {
      next = upsertSessionExport(next, { job: result.value, label: null })
      errors.delete(id)
    }
    else if (result.reason instanceof ApiError && !result.reason.isRetryable) {
      errors.set(id, message(result.reason))
    }
  })
  pollErrors.value = Object.fromEntries(errors)
  save(next)
}

function schedule(): void {
  if (timer) clearTimeout(timer)
  timer = null
  if (stopped) return
  const ids = pending.value.map(entry => entry.job.id).filter(id => !pollErrors.value[id])
  if (ids.length === 0) return
  timer = setTimeout(async () => {
    await refresh(ids)
    schedule()
  }, props.pollIntervalMs)
}

onMounted(async () => {
  if (!allowed.value) return
  entries.value = readSessionExports()
  const ids = pending.value.map(entry => entry.job.id)
  if (ids.length > 0) await refresh(ids)
  schedule()
})

onBeforeUnmount(() => {
  stopped = true
  if (timer) clearTimeout(timer)
})

async function onSubmit(request: CreateExportRequest, label: string | null): Promise<void> {
  creating.value = true
  formError.value = null
  serverErrors.value = {}
  try {
    const job = await createExportJob(request)
    save(upsertSessionExport(entries.value, { job, label }))
    toast.info(t('integrations.exports.started'))
    schedule()
  }
  catch (error) {
    const bound = bind(error, ['competition_id', 'from', 'to', 'type', 'format'])
    serverErrors.value = bound.fields
    formError.value = bound.unmatched[0] ?? (Object.keys(bound.fields).length > 0 ? null : message(error))
  }
  finally {
    creating.value = false
  }
}

async function download(job: ExportJob): Promise<void> {
  if (!job.file) return
  downloadingId.value = job.id
  try {
    await files.download(job.file.download_path, job.file.name)
  }
  catch (error) {
    toast.error(message(error))
  }
  finally {
    downloadingId.value = null
  }
}

const columns = computed<TableColumn[]>(() => [
  { key: 'type', label: t('integrations.exports.jobs.fields.type'), primary: true },
  { key: 'status', label: t('integrations.exports.jobs.fields.status') },
  { key: 'rows', label: t('integrations.exports.jobs.fields.rows'), numeric: true },
  { key: 'created_at', label: t('integrations.exports.jobs.fields.created') },
  { key: 'file', label: t('integrations.exports.jobs.fields.file'), align: 'end' },
])

const row = (value: unknown) => value as SessionExport
const rowKey = (value: object) => (value as SessionExport).job.id
const rangeText = (job: ExportJob) => {
  const from = typeof job.filters.from === 'string' ? job.filters.from : null
  const to = typeof job.filters.to === 'string' ? job.filters.to : null
  if (!from && !to) return null
  return t('integrations.exports.jobs.range', { from: from ?? '…', to: to ?? '…' })
}
</script>

<template>
  <div class="flex flex-col gap-6">
    <UiPageHeader
      :title="t('integrations.exports.title')"
      :description="t('integrations.exports.subtitle')"
    >
      <template #eyebrow>
        <UiButton
          variant="link"
          size="sm"
          class="mb-1"
          to="/dashboard/integrations"
        >
          {{ t('integrations.back') }}
        </UiButton>
      </template>
    </UiPageHeader>

    <UiForbiddenState v-if="!allowed" />

    <div
      v-else
      class="grid grid-cols-1 items-start gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)]"
    >
      <UiCard
        as="section"
        :title="t('integrations.exports.form.title')"
      >
        <IntegrationsExportForm
          :initial-type="initialType"
          :busy="creating"
          :form-error="formError"
          :server-errors="serverErrors"
          @submit="onSubmit"
        />
      </UiCard>

      <section
        class="flex flex-col gap-3"
        aria-labelledby="export-jobs-title"
      >
        <div>
          <h2
            id="export-jobs-title"
            class="text-lg font-bold text-fg"
          >
            {{ t('integrations.exports.jobs.title') }}
          </h2>
          <p class="text-sm text-fg-muted">
            {{ t('integrations.exports.jobs.hint') }}
          </p>
        </div>
        <UiTable
          :columns="columns"
          :rows="entries"
          :row-key="rowKey"
          :caption="t('integrations.exports.jobs.title')"
        >
          <template #cell-type="{ row: r }">
            <span class="flex min-w-0 flex-col gap-0.5">
              <span class="font-semibold text-fg">
                {{ t(`integrations.exports.types.${row(r).job.type}.label`) }}
                <bdi class="text-xs font-normal text-fg-muted uppercase">{{ row(r).job.format }}</bdi>
              </span>
              <span
                v-if="row(r).label"
                class="truncate text-xs text-fg-muted"
              >{{ row(r).label }}</span>
              <span
                v-else-if="rangeText(row(r).job)"
                class="text-xs text-fg-muted"
              ><bdi>{{ rangeText(row(r).job) }}</bdi></span>
            </span>
          </template>
          <template #cell-status="{ row: r }">
            <span
              class="flex flex-col items-start gap-1"
              aria-live="polite"
            >
              <UiBadge
                :tone="JOB_STATUS_TONES[row(r).job.status] ?? 'neutral'"
                size="sm"
                dot
              >
                {{ t(`integrations.jobs.statuses.${row(r).job.status}`) }}
              </UiBadge>
              <span
                v-if="row(r).job.status === 'failed'"
                class="text-xs text-fg-muted"
              >{{ row(r).job.failure_message || t('integrations.exports.failed') }}</span>
              <span
                v-if="pollErrors[row(r).job.id]"
                class="text-xs text-danger"
              >{{ pollErrors[row(r).job.id] }}</span>
            </span>
          </template>
          <template #cell-rows="{ row: r }">
            <bdi v-if="row(r).job.row_count !== null">{{ row(r).job.row_count }}</bdi>
            <span v-else>—</span>
          </template>
          <template #cell-created_at="{ row: r }">
            <UiDateTime :value="row(r).job.created_at" />
          </template>
          <template #cell-file="{ row: r }">
            <UiButton
              v-if="row(r).job.status === 'completed' && row(r).job.file"
              variant="secondary"
              size="sm"
              :icon="Download"
              :loading="downloadingId === row(r).job.id"
              @click="download(row(r).job)"
            >
              {{ t('integrations.exports.jobs.download') }}
            </UiButton>
            <UiProgress
              v-else-if="!isTerminal(row(r).job)"
              class="ms-auto max-w-24"
              :label="t(`integrations.jobs.statuses.${row(r).job.status}`)"
            />
            <span v-else>—</span>
          </template>
          <template #empty>
            <UiEmptyState
              compact
              :icon="FileDown"
              :title="t('integrations.exports.jobs.empty.title')"
              :description="t('integrations.exports.jobs.empty.body')"
            />
          </template>
        </UiTable>
      </section>
    </div>
  </div>
</template>
