<script setup lang="ts">
import type { Component } from 'vue'
import { ArrowDown, ArrowUp, Download, ExternalLink, FileArchive, FileImage, FileSpreadsheet, FileText, Link2, Trash2, Upload } from '@lucide/vue'
import { createLinkAttachment, deleteAttachment, listAttachments, updateAttachment, uploadAttachmentWithProgress } from '~/services/competitions'
import type { Attachment, AttachmentKind, CompetitionStatus } from '~/types/api/competitions'
import type { CompetitionContext } from '~/composables/useCompetitionContext'

/**
 * Competition documents for the issuer (SCREENS W15 step 5, W14 attachments): multi-file upload with
 * per-file kind («مستندات المنافسة (بعد الانضمام)» or «مستند الدعوة (قبل الانضمام)»), title and
 * progress; external https links; the list with type icon, size, kind and addendum badges, move up or
 * down, delete, and authenticated download (files are private, API.md §0.7).
 *
 * Adding is allowed in draft, scheduled and live (after publish an upload is an addendum); deleting in
 * draft and scheduled only (API.md §1.4). Per-row errors: `file_type_not_allowed`, `file_too_large`.
 */
const props = withDefaults(defineProps<{
  competitionId: string
  status: CompetitionStatus
  /** `permissions.can_edit` of the issuer projection. */
  canManage: boolean
  /** Overview mode: the upload form starts collapsed. */
  compact?: boolean
}>(), {
  compact: false,
})

const emit = defineEmits<{ changed: [count: number] }>()

const { t } = useI18n()
const toast = useToast()
const { message } = useErrorMessage()
const { download, downloading } = useFileDownload()

const ACCEPT = '.pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.zip'
const MAX_MB = 100

interface QueuedFile {
  id: number
  file: File
  kind: Exclude<AttachmentKind, 'external_link'>
  title: string
  progress: number | null
  state: 'ready' | 'uploading' | 'error'
  error: string | null
}

const attachments = ref<Attachment[]>([])
const loading = ref(true)
const loadError = ref<unknown>(null)
const picked = ref<File[]>([])
const queue = ref<QueuedFile[]>([])
const uploading = ref(false)
const formOpen = ref(!props.compact)
const linkUrl = ref('')
const linkTitle = ref('')
const linkBusy = ref(false)
const linkErrors = ref<{ url?: string, title?: string, form?: string }>({})
const busyId = ref<string | null>(null)
const deleteTarget = ref<Attachment | null>(null)
const deleteOpen = ref(false)
const deleting = ref(false)
const deleteError = ref<string | null>(null)
let queueSeq = 0

const canAdd = computed(() => props.canManage && ['draft', 'scheduled', 'live'].includes(props.status))
const canDelete = computed(() => props.canManage && ['draft', 'scheduled'].includes(props.status))
const sorted = computed(() => [...attachments.value].sort((a, b) => a.sort_order - b.sort_order))

let loadSeq = 0
async function load(): Promise<void> {
  const current = ++loadSeq
  loadError.value = null
  try {
    const list = await listAttachments(props.competitionId)
    if (current !== loadSeq) return
    attachments.value = list
    emit('changed', list.length)
  }
  catch (error) {
    if (current === loadSeq) loadError.value = error
  }
  finally {
    if (current === loadSeq) loading.value = false
  }
}

onMounted(load)

// Realtime: `competition.updated` with `attachments` refetches (SCREENS S4) when a detail context exists.
let context: CompetitionContext | null = null
try {
  context = useCompetitionContext()
}
catch {
  context = null
}
context?.on('competitionUpdated', (event) => {
  if (event.fields.includes('attachments')) void load()
})

watch(picked, (files) => {
  if (files.length === 0) return
  queue.value = [...queue.value, ...files.map(file => ({
    id: ++queueSeq,
    file,
    kind: 'document' as const,
    title: file.name.replace(/\.[^.]+$/, '').slice(0, 200),
    progress: null,
    state: 'ready' as const,
    error: null,
  }))]
  picked.value = []
})

function removeQueued(id: number): void {
  queue.value = queue.value.filter(item => item.id !== id)
}

function patchQueued(id: number, patch: Partial<QueuedFile>): void {
  queue.value = queue.value.map(item => (item.id === id ? { ...item, ...patch } : item))
}

async function uploadAll(): Promise<void> {
  uploading.value = true
  let uploaded = 0
  for (const item of queue.value.filter(entry => entry.state !== 'uploading')) {
    patchQueued(item.id, { state: 'uploading', progress: 0, error: null })
    try {
      const created = await uploadAttachmentWithProgress(props.competitionId, { file: item.file, kind: item.kind, title: item.title.trim() || null }, (progress) => {
        patchQueued(item.id, { progress: progress.total ? Math.round((progress.loaded / progress.total) * 100) : null })
      })
      attachments.value = [...attachments.value, created]
      removeQueued(item.id)
      uploaded += 1
    }
    catch (error) {
      patchQueued(item.id, { state: 'error', progress: null, error: message(error) })
    }
  }
  uploading.value = false
  if (uploaded > 0) {
    toast.success(t('competitions.issuer.attachments.uploaded', { count: uploaded }, uploaded))
    emit('changed', attachments.value.length)
  }
}

function validLink(): boolean {
  const errors: typeof linkErrors.value = {}
  if (!isHttpsUrl(linkUrl.value)) errors.url = t('validation.url_https')
  if (!linkTitle.value.trim()) errors.title = t('validation.required')
  linkErrors.value = errors
  return Object.keys(errors).length === 0
}

async function addLink(): Promise<void> {
  if (!validLink()) return
  linkBusy.value = true
  try {
    const created = await createLinkAttachment(props.competitionId, { kind: 'external_link', url: linkUrl.value.trim(), title: linkTitle.value.trim() })
    attachments.value = [...attachments.value, created]
    linkUrl.value = ''
    linkTitle.value = ''
    toast.success(t('competitions.issuer.attachments.link_added'))
    emit('changed', attachments.value.length)
  }
  catch (error) {
    const apiError = error instanceof ApiError ? error : null
    linkErrors.value = {
      url: apiError?.fieldError('url'),
      title: apiError?.fieldError('title'),
      form: apiError?.isValidation ? undefined : message(error),
    }
  }
  finally {
    linkBusy.value = false
  }
}

/**
 * Moves an attachment one place, renumbering `sort_order` 0…n−1 (the stored values may tie), then
 * reloads: the list only changes once the server has answered (no optimistic reorder).
 */
async function move(attachment: Attachment, delta: -1 | 1): Promise<void> {
  const order = [...sorted.value]
  const index = order.findIndex(item => item.id === attachment.id)
  const other = order[index + delta]
  if (!other) return
  order[index] = other
  order[index + delta] = attachment
  busyId.value = attachment.id
  try {
    for (const [position, item] of order.entries()) {
      if (item.sort_order !== position) await updateAttachment(props.competitionId, item.id, { sort_order: position })
    }
    await load()
  }
  catch (error) {
    toast.error(message(error))
  }
  finally {
    busyId.value = null
  }
}

function askDelete(attachment: Attachment): void {
  deleteTarget.value = attachment
  deleteError.value = null
  deleteOpen.value = true
}

async function confirmDelete(): Promise<void> {
  const target = deleteTarget.value
  if (!target) return
  deleting.value = true
  deleteError.value = null
  try {
    await deleteAttachment(props.competitionId, target.id)
    attachments.value = attachments.value.filter(item => item.id !== target.id)
    deleteOpen.value = false
    toast.success(t('competitions.issuer.attachments.deleted'))
    emit('changed', attachments.value.length)
  }
  catch (error) {
    deleteError.value = message(error)
  }
  finally {
    deleting.value = false
  }
}

async function downloadFile(attachment: Attachment): Promise<void> {
  if (!attachment.file) return
  try {
    await download(attachment.file.download_path, attachment.file.name)
  }
  catch (error) {
    toast.error(message(error))
  }
}

function iconFor(attachment: Attachment): Component {
  if (attachment.kind === 'external_link') return Link2
  const extension = attachment.file?.extension.toLowerCase() ?? ''
  if (['xls', 'xlsx'].includes(extension)) return FileSpreadsheet
  if (['png', 'jpg', 'jpeg'].includes(extension)) return FileImage
  if (extension === 'zip') return FileArchive
  return FileText
}

function sizeLabel(bytes: number): string {
  const { value, unit } = fileSizeParts(bytes)
  return t(`common.file_drop.units.${unit}`, { value })
}

const kindOptions = computed(() => (['document', 'invitation_document'] as const).map(kind => ({
  value: kind,
  label: t(`competitions.issuer.attachments.kinds.${kind}`),
})))
</script>

<template>
  <div class="flex flex-col gap-5">
    <UiCard
      v-if="canAdd"
      padding="none"
    >
      <template #header>
        <div class="flex w-full flex-wrap items-center justify-between gap-3">
          <div class="min-w-0">
            <h3 class="text-base font-bold text-fg">
              {{ t('competitions.issuer.attachments.add_title') }}
            </h3>
            <p class="mt-0.5 text-sm text-fg-muted">
              {{ status === 'draft' ? t('competitions.issuer.attachments.add_hint') : t('competitions.issuer.attachments.addendum_hint') }}
            </p>
          </div>
          <UiButton
            v-if="compact"
            variant="secondary"
            size="sm"
            :aria-expanded="formOpen"
            @click="formOpen = !formOpen"
          >
            {{ formOpen ? t('common.actions.close') : t('competitions.issuer.attachments.add_open') }}
          </UiButton>
        </div>
      </template>
      <div
        v-if="formOpen"
        class="flex flex-col gap-6 p-5 sm:p-6"
      >
        <UiFileDrop
          v-model="picked"
          :label="t('competitions.issuer.attachments.files_label')"
          :hint="t('competitions.issuer.attachments.files_hint')"
          :accept="ACCEPT"
          :max-size-mb="MAX_MB"
          :max-files="10"
          multiple
        />
        <ul
          v-if="queue.length > 0"
          class="flex flex-col gap-3"
          :aria-label="t('competitions.issuer.attachments.queue_label')"
        >
          <li
            v-for="item in queue"
            :key="item.id"
            class="flex flex-col gap-3 rounded-md border border-line p-3"
          >
            <div class="flex items-center justify-between gap-2">
              <span class="min-w-0 truncate text-sm font-semibold text-fg">
                <bdi dir="ltr">{{ item.file.name }}</bdi>
                <span class="ms-2 font-normal text-fg-muted">{{ sizeLabel(item.file.size) }}</span>
              </span>
              <UiIconButton
                :icon="Trash2"
                :label="t('common.file_drop.remove', { name: item.file.name })"
                size="sm"
                variant="danger-ghost"
                :disabled="item.state === 'uploading'"
                @click="removeQueued(item.id)"
              />
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
              <UiSelect
                :model-value="item.kind"
                :options="kindOptions"
                :label="t('competitions.issuer.attachments.kind_label')"
                :disabled="item.state === 'uploading'"
                @update:model-value="value => value && patchQueued(item.id, { kind: value })"
              />
              <UiInput
                :model-value="item.title"
                :label="t('competitions.issuer.attachments.title_label')"
                :maxlength="200"
                :disabled="item.state === 'uploading'"
                @update:model-value="value => patchQueued(item.id, { title: value })"
              />
            </div>
            <UiProgress
              v-if="item.state === 'uploading'"
              :label="t('competitions.issuer.attachments.uploading', { name: item.file.name })"
              :value="item.progress"
            />
            <p
              v-if="item.error"
              class="text-sm text-danger"
              role="alert"
            >
              {{ item.error }}
            </p>
          </li>
        </ul>
        <div
          v-if="queue.length > 0"
          class="flex justify-end"
        >
          <UiButton
            :icon="Upload"
            :loading="uploading"
            @click="uploadAll"
          >
            {{ t('competitions.issuer.attachments.upload', { count: queue.length }, queue.length) }}
          </UiButton>
        </div>

        <form
          class="flex flex-col gap-3 border-t border-line pt-5"
          novalidate
          @submit.prevent="addLink"
        >
          <p class="text-sm font-bold text-fg">
            {{ t('competitions.issuer.attachments.link_title') }}
          </p>
          <div class="grid gap-3 sm:grid-cols-2">
            <UiInput
              v-model="linkUrl"
              type="url"
              dir="ltr"
              :label="t('competitions.issuer.attachments.link_url')"
              :error="linkErrors.url"
              :maxlength="1000"
              placeholder="https://"
            />
            <UiInput
              v-model="linkTitle"
              :label="t('competitions.issuer.attachments.link_name')"
              :error="linkErrors.title"
              :maxlength="200"
            />
          </div>
          <UiAlert
            v-if="linkErrors.form"
            tone="danger"
          >
            {{ linkErrors.form }}
          </UiAlert>
          <div class="flex justify-end">
            <UiButton
              type="submit"
              variant="secondary"
              :icon="Link2"
              :loading="linkBusy"
            >
              {{ t('competitions.issuer.attachments.link_add') }}
            </UiButton>
          </div>
        </form>
      </div>
    </UiCard>

    <UiCard
      padding="none"
      :title="t('competitions.issuer.attachments.list_title')"
    >
      <div
        v-if="loading"
        class="flex flex-col gap-3 p-5"
      >
        <UiSkeleton class="h-10 w-full" />
        <UiSkeleton class="h-10 w-full" />
      </div>
      <UiErrorState
        v-else-if="loadError"
        :error="loadError"
        compact
        @retry="load"
      />
      <UiEmptyState
        v-else-if="sorted.length === 0"
        :title="t('competitions.issuer.attachments.empty_title')"
        :description="t('competitions.issuer.attachments.empty_body')"
        :icon="FileText"
        compact
      />
      <ul
        v-else
        class="divide-y divide-line"
      >
        <li
          v-for="(attachment, index) in sorted"
          :key="attachment.id"
          class="flex flex-wrap items-center gap-3 px-5 py-3"
        >
          <component
            :is="iconFor(attachment)"
            :size="20"
            class="shrink-0 text-fg-muted"
            aria-hidden="true"
          />
          <div class="flex min-w-0 flex-1 flex-col gap-1">
            <span class="truncate font-semibold text-fg">{{ attachment.title || attachment.file?.name || attachment.url }}</span>
            <span class="flex flex-wrap items-center gap-2 text-xs text-fg-muted">
              <span v-if="attachment.file"><bdi dir="ltr">{{ attachment.file.name }}</bdi> · {{ sizeLabel(attachment.file.size_bytes) }}</span>
              <bdi
                v-else-if="attachment.url"
                dir="ltr"
                class="truncate"
              >{{ attachment.url }}</bdi>
              <UiBadge
                size="sm"
                :tone="attachment.kind === 'invitation_document' ? 'info' : 'neutral'"
              >
                {{ t(`competitions.issuer.attachments.kinds.${attachment.kind}`) }}
              </UiBadge>
              <UiBadge
                v-if="attachment.is_addendum"
                size="sm"
                tone="warning"
              >
                {{ t('competitions.issuer.attachments.addendum') }}
              </UiBadge>
            </span>
          </div>
          <div class="flex items-center gap-1">
            <UiIconButton
              v-if="attachment.file"
              :icon="Download"
              :label="t('competitions.issuer.attachments.download', { name: attachment.title || attachment.file.name })"
              size="sm"
              :disabled="downloading"
              @click="downloadFile(attachment)"
            />
            <a
              v-else-if="attachment.url"
              :href="attachment.url"
              target="_blank"
              rel="noopener noreferrer"
              class="inline-flex size-8 items-center justify-center rounded-md text-fg-muted hover:bg-surface-muted hover:text-fg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
              :aria-label="t('competitions.issuer.attachments.open_link', { name: attachment.title })"
            >
              <ExternalLink
                :size="16"
                aria-hidden="true"
              />
            </a>
            <template v-if="canAdd">
              <UiIconButton
                :icon="ArrowUp"
                :label="t('competitions.issuer.attachments.move_up')"
                size="sm"
                :disabled="index === 0 || busyId !== null"
                @click="move(attachment, -1)"
              />
              <UiIconButton
                :icon="ArrowDown"
                :label="t('competitions.issuer.attachments.move_down')"
                size="sm"
                :disabled="index === sorted.length - 1 || busyId !== null"
                @click="move(attachment, 1)"
              />
            </template>
            <UiIconButton
              v-if="canDelete"
              :icon="Trash2"
              :label="t('competitions.issuer.attachments.delete', { name: attachment.title || attachment.file?.name || '' })"
              size="sm"
              variant="danger-ghost"
              @click="askDelete(attachment)"
            />
          </div>
        </li>
      </ul>
    </UiCard>

    <UiConfirmDialog
      v-model:open="deleteOpen"
      :title="t('competitions.issuer.attachments.delete_title')"
      :description="t('competitions.issuer.attachments.delete_body', { name: deleteTarget?.title || deleteTarget?.file?.name || '' })"
      :confirm-label="t('competitions.issuer.attachments.delete_confirm')"
      :busy="deleting"
      :error="deleteError"
      danger
      @confirm="confirmDelete"
    />
  </div>
</template>
