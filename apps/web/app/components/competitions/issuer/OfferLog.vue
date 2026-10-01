<script setup lang="ts">
import { Lock, Tag } from '@lucide/vue'
import { fetchOfferLog } from '~/services/bidding'
import type { OfferLogEntry } from '~/types/api/bidding'
import type { TableColumn } from '~/types/ui'
import { lastOfferSeq, mergeOfferEntries, offerSeqGap } from '~/stores/competition-editor-console'

/**
 * W20 offers log (SCREENS §2.4 `OfferLogTable`): `GET …/offers/log?after_seq=&limit=200`, "Load more"
 * while `has_more`; each row has the sequence, the machine time `yyyy-MM-dd HH:mm:ss.SSS (KSA)`, the
 * alias and organisation, the amount («مغلق» while sealed), the stage, the channel and a «ملغى»
 * badge. Realtime `offer.accepted` appends by `seq`; a gap triggers `after_seq={lastSeq}` (S4).
 */
const props = defineProps<{ competitionId: string }>()

const ctx = useCompetitionContext()
const { t } = useI18n()

const entries = ref<OfferLogEntry[]>([])
const loading = ref(true)
const loadingMore = ref(false)
const hasMore = ref(false)
const error = ref<unknown>(null)
let filling = false

async function loadPage(afterSeq: number): Promise<void> {
  const page = await fetchOfferLog(props.competitionId, afterSeq, 200)
  entries.value = mergeOfferEntries(entries.value, page.entries)
  hasMore.value = page.has_more
}

async function load(): Promise<void> {
  loading.value = true
  error.value = null
  try {
    entries.value = []
    await loadPage(0)
  }
  catch (cause) {
    error.value = cause
  }
  finally {
    loading.value = false
  }
}

async function loadMore(): Promise<void> {
  loadingMore.value = true
  try {
    await loadPage(lastOfferSeq(entries.value))
  }
  catch (cause) {
    error.value = cause
  }
  finally {
    loadingMore.value = false
  }
}

/** Fills a realtime gap (only when everything before it is already loaded). */
async function fillGap(afterSeq: number): Promise<void> {
  if (filling) return
  filling = true
  try {
    let cursor = afterSeq
    for (;;) {
      const page = await fetchOfferLog(props.competitionId, cursor, 200)
      entries.value = mergeOfferEntries(entries.value, page.entries)
      if (!page.has_more || page.last_seq <= cursor) break
      cursor = page.last_seq
    }
  }
  catch {
    // The next event or a reload fills it again.
  }
  finally {
    filling = false
  }
}

onMounted(load)

ctx.on('offerAccepted', (entry) => {
  if (hasMore.value) return // older pages are still loading on demand: the entry arrives with them
  const last = lastOfferSeq(entries.value)
  if (offerSeqGap(last, entry)) void fillGap(last)
  entries.value = mergeOfferEntries(entries.value, [entry])
})

/** Ascending `seq`, like the API pages, so "Load more" continues at the end. */
const rows = computed(() => entries.value)

const columns = computed<TableColumn[]>(() => [
  { key: 'seq', label: t('offers.log.columns.seq'), numeric: true },
  { key: 'time', label: t('offers.log.columns.time'), primary: true },
  { key: 'participant', label: t('offers.log.columns.participant') },
  { key: 'amount', label: t('offers.log.columns.amount'), numeric: true },
  { key: 'stage', label: t('offers.log.columns.stage') },
  { key: 'channel', label: t('offers.log.columns.channel'), hideOnMobile: true },
])

const asEntry = (row: unknown) => row as OfferLogEntry
</script>

<template>
  <div class="flex flex-col gap-4">
    <UiCard
      v-if="error && entries.length === 0"
      padding="none"
    >
      <UiErrorState
        :error="error"
        @retry="load"
      />
    </UiCard>
    <UiTable
      v-else
      :columns="columns"
      :rows="rows"
      row-key="id"
      :caption="t('offers.log.caption')"
      :loading="loading"
    >
      <template #cell-seq="{ row }">
        <bdi class="tabular-nums">{{ asEntry(row).seq }}</bdi>
      </template>
      <template #cell-time="{ row }">
        <span class="flex flex-wrap items-center gap-2">
          <bdi class="tabular-nums">{{ t('offers.machine_time', { time: formatMachineTime(asEntry(row).accepted_at) ?? '—' }) }}</bdi>
          <UiBadge
            v-if="asEntry(row).voided"
            size="sm"
            tone="neutral"
          >
            {{ t('offers.log.voided') }}
          </UiBadge>
        </span>
      </template>
      <template #cell-participant="{ row }">
        <span class="flex min-w-0 flex-col">
          <span class="font-semibold text-fg">{{ t('offers.participant_alias', { number: asEntry(row).participant.alias_no }) }}</span>
          <span class="truncate text-xs text-fg-muted">{{ asEntry(row).participant.organization.name }}</span>
        </span>
      </template>
      <template #cell-amount="{ row }">
        <UiAmount
          v-if="asEntry(row).amount_minor !== null"
          :minor="asEntry(row).amount_minor"
          :class="asEntry(row).voided && 'line-through'"
        />
        <span
          v-else
          class="inline-flex items-center gap-1 text-fg-muted"
        ><Lock
          :size="14"
          aria-hidden="true"
        />{{ t('offers.sealed_amount') }}</span>
      </template>
      <template #cell-stage="{ row }">
        <UiBadge
          size="sm"
          tone="neutral"
        >
          {{ t(`offers.stage.${asEntry(row).stage}`) }}
        </UiBadge>
      </template>
      <template #cell-channel="{ row }">
        {{ t(`offers.channel.${asEntry(row).channel}`) }}
      </template>
      <template #empty>
        <UiEmptyState
          :title="t('offers.log.empty_title')"
          :description="t('offers.log.empty_body')"
          :icon="Tag"
        />
      </template>
    </UiTable>
    <div
      v-if="hasMore"
      class="flex justify-center"
    >
      <UiButton
        variant="secondary"
        :loading="loadingMore"
        @click="loadMore"
      >
        {{ t('offers.log.load_more') }}
      </UiButton>
    </div>
  </div>
</template>
