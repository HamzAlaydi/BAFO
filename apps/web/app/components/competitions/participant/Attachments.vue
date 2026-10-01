<script setup lang="ts">
import { Download, ExternalLink, FileText, Link2 } from '@lucide/vue'
import type { Attachment } from '~/types/api/competitions'

/**
 * Competition documents for participants and invitees (SCREENS W14): files the server lets this
 * viewer see (ARCHITECTURE §8.5: joined participants get `document` and `external_link`, invitees
 * `invitation_document` only). Files download as a blob with the bearer header (API.md §0.7);
 * links open in a new tab. Addenda added after publishing carry a badge.
 */
const props = defineProps<{
  attachments: Attachment[]
  title: string
  loading?: boolean
  error?: unknown
}>()

const emit = defineEmits<{ retry: [] }>()
const { t } = useI18n()
const toast = useToast()
const { message } = useErrorMessage()
const { download } = useFileDownload()
const downloadingId = ref<string | null>(null)

const sorted = computed(() => [...props.attachments].sort((a, b) => a.sort_order - b.sort_order))

function label(attachment: Attachment): string {
  return attachment.title?.trim() || attachment.file?.name || attachment.url || ''
}

function size(attachment: Attachment): string | null {
  if (!attachment.file) return null
  const parts = fileSizeParts(attachment.file.size_bytes)
  return `${parts.value} ${t(`common.file_drop.units.${parts.unit}`)}`
}

async function save(attachment: Attachment): Promise<void> {
  if (!attachment.file || downloadingId.value) return
  downloadingId.value = attachment.id
  try {
    await download(attachment.file.download_path, attachment.file.name)
  }
  catch (error) {
    toast.error(message(error))
  }
  finally {
    downloadingId.value = null
  }
}
</script>

<template>
  <UiCard
    :title="title"
    padding="none"
  >
    <div
      v-if="loading && attachments.length === 0"
      class="flex flex-col gap-3 p-5"
      aria-busy="true"
      :aria-label="t('common.loading')"
    >
      <UiSkeleton class="h-10" />
      <UiSkeleton class="h-10" />
    </div>
    <UiErrorState
      v-else-if="error && attachments.length === 0"
      :error="error"
      compact
      @retry="emit('retry')"
    />
    <p
      v-else-if="attachments.length === 0"
      class="p-5 text-sm text-fg-muted"
    >
      {{ t('competitions.participant.attachments.empty') }}
    </p>
    <ul
      v-else
      class="divide-y divide-line"
      data-testid="attachments"
    >
      <li
        v-for="attachment in sorted"
        :key="attachment.id"
        class="flex flex-wrap items-center gap-3 px-4 py-3 sm:px-5"
      >
        <span
          class="flex size-9 shrink-0 items-center justify-center rounded-md bg-surface-muted text-fg-muted"
          aria-hidden="true"
        >
          <component
            :is="attachment.kind === 'external_link' ? Link2 : FileText"
            :size="18"
          />
        </span>
        <div class="min-w-0 flex-1">
          <p class="flex flex-wrap items-center gap-2 font-semibold text-fg">
            <span class="min-w-0 break-words">{{ label(attachment) }}</span>
            <UiBadge
              v-if="attachment.is_addendum"
              tone="info"
              size="sm"
            >
              {{ t('competitions.participant.attachments.addendum') }}
            </UiBadge>
          </p>
          <p
            v-if="attachment.file"
            class="text-xs text-fg-muted"
          >
            <bdi class="uppercase">{{ attachment.file.extension }}</bdi>
            <template v-if="size(attachment)">
              · <bdi>{{ size(attachment) }}</bdi>
            </template>
          </p>
        </div>
        <a
          v-if="attachment.kind === 'external_link' && attachment.url"
          :href="attachment.url"
          target="_blank"
          rel="noopener noreferrer"
          class="inline-flex h-9 items-center gap-1.5 rounded-md border border-line px-3 text-sm font-semibold text-fg hover:bg-surface-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
        >
          {{ t('competitions.participant.attachments.open_link') }}
          <ExternalLink
            :size="14"
            aria-hidden="true"
          />
          <span class="sr-only">{{ label(attachment) }} · {{ t('competitions.participant.new_tab') }}</span>
        </a>
        <UiButton
          v-else-if="attachment.file"
          variant="secondary"
          size="sm"
          :icon="Download"
          :loading="downloadingId === attachment.id"
          :aria-label="t('competitions.participant.attachments.download', { name: label(attachment) })"
          @click="save(attachment)"
        >
          {{ t('common.actions.download') }}
        </UiButton>
      </li>
    </ul>
  </UiCard>
</template>
