<script setup lang="ts">
import { CircleCheck, Download, FileSpreadsheet, RotateCcw, Upload } from '@lucide/vue'
import { createImportJob, fetchImportJob, importTemplatePath } from '~/services/integrations'
import type { ExportFormat, ImportJob } from '~/types/api/integrations'
import type { StepItem } from '~/types/ui'

/**
 * `ImportWizard` (SCREENS W41; ARCHITECTURE §14.7): 1 download a template, 2 upload a CSV/XLSX
 * (≤ 20 MB) and validate it (`mode: validate`, polled every 2 s), 3 review totals and row errors
 * (errors file download), 4 import the valid rows by re-posting the same file (`mode: commit`).
 * The file stays in memory between steps; the parent guards leaving the page (`dirty`).
 */
const props = withDefaults(defineProps<{ pollIntervalMs?: number }>(), { pollIntervalMs: 2000 })
const emit = defineEmits<{ dirty: [value: boolean] }>()

const { t } = useI18n()
const { message } = useErrorMessage()
const files = useFileDownload()

type Phase = 'upload' | 'validating' | 'reviewed' | 'committing' | 'done'

const phase = ref<Phase>('upload')
const selectedFiles = ref<File[]>([])
const validateJob = ref<ImportJob | null>(null)
const commitJob = ref<ImportJob | null>(null)
const startError = ref<string | null>(null)
const starting = ref(false)
const downloading = ref<string | null>(null)
const downloadError = ref<string | null>(null)
let currentJobId: string | null = null

const file = computed(() => selectedFiles.value[0] ?? null)
const poll = useJobPoll<ImportJob>(() => fetchImportJob(currentJobId ?? ''), { intervalMs: props.pollIntervalMs })

const dirty = computed(() => file.value !== null && phase.value !== 'done')
watch(dirty, value => emit('dirty', value), { immediate: true })

watch(() => poll.data.value, (job) => {
  if (!job) return
  if (job.mode === 'validate' && phase.value === 'validating') {
    validateJob.value = job
    if (job.status === 'completed' || job.status === 'failed') phase.value = 'reviewed'
  }
  if (job.mode === 'commit' && phase.value === 'committing') {
    commitJob.value = job
    if (job.status === 'completed') phase.value = 'done'
    else if (job.status === 'failed') phase.value = 'reviewed'
  }
})

const pollError = computed(() => (poll.error.value ? message(poll.error.value) : null))

const steps = computed<StepItem[]>(() => (['template', 'upload', 'review', 'import'] as const).map(key => ({
  key,
  label: t(`integrations.import.steps.${key}`),
})))
const stepIndex = computed(() => {
  if (phase.value === 'upload') return file.value ? 1 : 0
  if (phase.value === 'validating') return 1
  if (phase.value === 'reviewed') return 2
  return 3
})

async function downloadTemplate(format: ExportFormat): Promise<void> {
  downloading.value = format
  downloadError.value = null
  try {
    await files.download(importTemplatePath(format), `vendors-template.${format}`)
  }
  catch (error) {
    downloadError.value = message(error)
  }
  finally {
    downloading.value = null
  }
}

async function start(mode: 'validate' | 'commit'): Promise<void> {
  const current = file.value
  if (!current || starting.value) return
  starting.value = true
  startError.value = null
  try {
    const job = await createImportJob(current, mode)
    currentJobId = job.id
    if (mode === 'validate') {
      validateJob.value = job
      commitJob.value = null
      phase.value = 'validating'
    }
    else {
      commitJob.value = job
      phase.value = 'committing'
    }
    poll.start(job)
  }
  catch (error) {
    startError.value = message(error)
  }
  finally {
    starting.value = false
  }
}

async function downloadErrors(): Promise<void> {
  const errorsFile = (commitJob.value?.errors_file ?? validateJob.value?.errors_file) ?? null
  if (!errorsFile) return
  downloading.value = 'errors'
  downloadError.value = null
  try {
    await files.download(errorsFile.download_path, errorsFile.name)
  }
  catch (error) {
    downloadError.value = message(error)
  }
  finally {
    downloading.value = null
  }
}

function reset(): void {
  poll.stop()
  currentJobId = null
  selectedFiles.value = []
  validateJob.value = null
  commitJob.value = null
  startError.value = null
  phase.value = 'upload'
}

watch(file, () => {
  if (phase.value !== 'upload') return
  validateJob.value = null
  startError.value = null
})

const validated = computed(() => (validateJob.value?.status === 'completed' ? validateJob.value : null))
const validRows = computed(() => validated.value?.valid_rows ?? 0)
const reviewJob = computed(() => (commitJob.value?.status === 'failed' ? commitJob.value : validateJob.value))
</script>

<template>
  <div class="flex flex-col gap-6">
    <UiStepper
      :steps="steps"
      :current="stepIndex"
    />

    <!-- 1. Template -->
    <UiCard
      as="section"
      :title="t('integrations.import.template.title')"
      :description="t('integrations.import.template.body')"
    >
      <div class="flex flex-wrap gap-2">
        <UiButton
          variant="secondary"
          :icon="Download"
          :loading="downloading === 'csv'"
          @click="downloadTemplate('csv')"
        >
          {{ t('integrations.import.template.csv') }}
        </UiButton>
        <UiButton
          variant="secondary"
          :icon="FileSpreadsheet"
          :loading="downloading === 'xlsx'"
          @click="downloadTemplate('xlsx')"
        >
          {{ t('integrations.import.template.xlsx') }}
        </UiButton>
      </div>
      <UiAlert
        v-if="downloadError"
        class="mt-4"
        tone="danger"
        role="alert"
      >
        {{ downloadError }}
      </UiAlert>
    </UiCard>

    <!-- 2. Upload and validate -->
    <UiCard
      as="section"
      :title="t('integrations.import.upload.title')"
    >
      <div class="flex flex-col gap-4">
        <UiFileDrop
          v-model="selectedFiles"
          :label="t('integrations.import.upload.label')"
          :hint="t('integrations.import.upload.hint')"
          accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
          :max-size-mb="20"
          :disabled="phase !== 'upload'"
          required
        />
        <UiAlert
          v-if="startError"
          tone="danger"
          role="alert"
        >
          {{ startError }}
        </UiAlert>
        <div
          v-if="phase === 'upload'"
          class="flex justify-end"
        >
          <UiButton
            :icon="Upload"
            :loading="starting"
            :disabled="!file"
            @click="start('validate')"
          >
            {{ t('integrations.import.upload.validate') }}
          </UiButton>
        </div>
        <IntegrationsJobProgress
          v-if="phase === 'validating' && validateJob"
          :status="validateJob.status"
          :label="t('integrations.import.progress_validate')"
        />
        <UiAlert
          v-if="pollError && (phase === 'validating' || phase === 'committing')"
          tone="danger"
          role="alert"
        >
          {{ pollError }}
          <UiButton
            class="mt-2"
            size="sm"
            variant="secondary"
            @click="poll.start()"
          >
            {{ t('common.actions.retry') }}
          </UiButton>
        </UiAlert>
      </div>
    </UiCard>

    <!-- 3. Review -->
    <UiCard
      v-if="(phase === 'reviewed' || phase === 'committing') && reviewJob"
      as="section"
      :title="t('integrations.import.results.title')"
    >
      <div class="flex flex-col gap-5">
        <UiAlert
          v-if="reviewJob.status === 'failed'"
          tone="danger"
          role="alert"
        >
          {{ reviewJob.failure_message || t('integrations.import.failed') }}
        </UiAlert>
        <template v-else>
          <dl class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="rounded-md bg-surface-muted p-4">
              <dt class="text-sm text-fg-muted">
                {{ t('integrations.import.results.total') }}
              </dt>
              <dd class="mt-1 text-2xl font-bold text-fg tabular-nums">
                <bdi>{{ reviewJob.total_rows ?? 0 }}</bdi>
              </dd>
            </div>
            <div class="rounded-md bg-primary-soft p-4">
              <dt class="text-sm text-primary-soft-fg">
                {{ t('integrations.import.results.valid') }}
              </dt>
              <dd class="mt-1 text-2xl font-bold text-primary-soft-fg tabular-nums">
                <bdi>{{ reviewJob.valid_rows ?? 0 }}</bdi>
              </dd>
            </div>
            <div class="rounded-md bg-warning-soft p-4">
              <dt class="text-sm text-warning-soft-fg">
                {{ t('integrations.import.results.errors') }}
              </dt>
              <dd class="mt-1 text-2xl font-bold text-warning-soft-fg tabular-nums">
                <bdi>{{ reviewJob.error_rows ?? 0 }}</bdi>
              </dd>
            </div>
          </dl>

          <template v-if="reviewJob.errors_preview.length > 0">
            <p class="text-sm text-fg-muted">
              {{ t('integrations.import.results.preview_note') }}
            </p>
            <IntegrationsImportErrorsTable :errors="reviewJob.errors_preview" />
          </template>
          <p
            v-else
            class="inline-flex items-center gap-2 text-sm font-semibold text-primary-soft-fg"
          >
            <CircleCheck
              :size="18"
              aria-hidden="true"
            />
            {{ t('integrations.import.results.no_errors') }}
          </p>

          <UiButton
            v-if="reviewJob.errors_file"
            variant="secondary"
            class="self-start"
            :icon="Download"
            :loading="downloading === 'errors'"
            @click="downloadErrors"
          >
            {{ t('integrations.import.results.download_errors') }}
          </UiButton>

          <UiAlert
            v-if="validRows === 0"
            tone="warning"
          >
            {{ t('integrations.import.results.none_valid') }}
          </UiAlert>
        </template>

        <IntegrationsJobProgress
          v-if="phase === 'committing' && commitJob"
          :status="commitJob.status"
          :label="t('integrations.import.progress_commit')"
        />

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-between">
          <UiButton
            variant="ghost"
            :icon="RotateCcw"
            :disabled="phase === 'committing'"
            @click="reset"
          >
            {{ t('integrations.import.start_over') }}
          </UiButton>
          <UiButton
            v-if="validated && validRows > 0"
            :loading="starting || phase === 'committing'"
            @click="start('commit')"
          >
            {{ t('integrations.import.commit', { count: validRows }, validRows) }}
          </UiButton>
        </div>
      </div>
    </UiCard>

    <!-- 4. Done -->
    <UiCard
      v-if="phase === 'done' && commitJob"
      as="section"
    >
      <div
        class="flex flex-col items-center gap-4 py-4 text-center"
        role="status"
      >
        <span
          class="inline-flex size-14 items-center justify-center rounded-full bg-primary-soft text-primary-soft-fg"
          aria-hidden="true"
        >
          <CircleCheck :size="28" />
        </span>
        <div>
          <h2 class="text-lg font-bold text-fg">
            {{ t('integrations.import.done.title') }}
          </h2>
          <p class="mt-1 text-fg-muted">
            {{ t('integrations.import.done.summary', { created: commitJob.created_rows ?? 0, updated: commitJob.updated_rows ?? 0 }) }}
          </p>
          <p
            v-if="(commitJob.error_rows ?? 0) > 0"
            class="mt-1 text-sm text-fg-muted"
          >
            {{ t('integrations.import.done.skipped', { count: commitJob.error_rows ?? 0 }, commitJob.error_rows ?? 0) }}
          </p>
        </div>
        <div class="flex flex-wrap justify-center gap-2">
          <UiButton to="/dashboard/vendors">
            {{ t('integrations.import.done.to_vendors') }}
          </UiButton>
          <UiButton
            variant="secondary"
            @click="reset"
          >
            {{ t('integrations.import.done.again') }}
          </UiButton>
        </div>
      </div>
    </UiCard>
  </div>
</template>
