<script setup lang="ts">
import { History, Radio, RefreshCw } from '@lucide/vue'
import type { ParticipantCompetition } from '~/types/api/competitions'
import type { TableColumn } from '~/types/ui'

/**
 * W21 My offers (SCREENS §2.4): `GET …/my-offers`, newest first, with the sequence number, the
 * server receipt time (`yyyy-MM-dd HH:mm:ss.SSS (KSA)`), the amount, the stage, a «ملغى» badge for
 * voided offers, and the change against the previous own offer: a neutral arrow and a percentage,
 * never coloured by direction (S2). Kept in step with applied snapshots through the live-room store.
 * Mount it in the My offers tab for participants; it reads `useCompetitionContext()`.
 */
const ctx = useCompetitionContext()
const store = useLiveRoomStore()
const { t } = useI18n()

const competition = computed(() => (ctx.competition.value?.viewer_role === 'participant' ? ctx.competition.value as ParticipantCompetition : null))
const rows = computed(() => (competition.value && store.competitionId === competition.value.id ? withOfferChanges(store.offers) : []))
const livePath = computed(() => (competition.value ? `/dashboard/competitions/${competition.value.id}/live` : null))

watch(() => competition.value?.id, (id) => {
  if (id) void store.load(id)
}, { immediate: true })

ctx.on('liveApplied', (snapshot) => {
  const id = competition.value?.id
  if (id && isParticipantSnapshot(snapshot)) store.syncWithSnapshot(id, snapshot)
})

watch(() => store.error, (error) => {
  if (error?.code === 'not_a_participant') void ctx.refetch()
})

const columns = computed<TableColumn[]>(() => [
  { key: 'seq', label: t('offers.mine.columns.seq'), numeric: true },
  { key: 'accepted_at', label: t('offers.mine.columns.time'), primary: true },
  { key: 'amount_minor', label: t('offers.mine.columns.amount'), numeric: true },
  { key: 'stage', label: t('offers.mine.columns.stage') },
  { key: 'change', label: t('offers.mine.columns.change') },
])

function time(iso: string): string {
  return t('offers.machine_time', { time: formatMachineTime(iso) ?? '—' })
}
</script>

<template>
  <div
    v-if="competition"
    class="flex flex-col gap-4"
    data-testid="participant-my-offers"
  >
    <div class="flex flex-wrap items-center justify-between gap-2">
      <h2 class="text-lg font-bold text-fg">
        {{ t('offers.mine.title') }}
      </h2>
      <UiButton
        variant="ghost"
        size="sm"
        :icon="RefreshCw"
        :loading="store.loading && store.loaded"
        @click="store.load(competition.id)"
      >
        {{ t('offers.mine.refresh') }}
      </UiButton>
    </div>

    <UiErrorState
      v-if="store.error && !store.loaded"
      :error="store.error"
      :retrying="store.loading"
      @retry="store.load(competition.id)"
    />
    <UiCard
      v-else-if="store.loaded && rows.length === 0"
      padding="none"
    >
      <UiEmptyState
        :icon="History"
        :title="t('offers.mine.empty.title')"
        :description="t('offers.mine.empty.body')"
      >
        <UiButton
          v-if="livePath"
          :to="livePath"
          :icon="Radio"
        >
          {{ t('offers.mine.empty.cta') }}
        </UiButton>
      </UiEmptyState>
    </UiCard>
    <UiTable
      v-else
      :columns="columns"
      :rows="rows"
      row-key="id"
      :caption="t('offers.mine.caption')"
      :loading="!store.loaded"
    >
      <template #cell-seq="{ row }">
        <bdi>{{ `#${row.seq}` }}</bdi>
      </template>
      <template #cell-accepted_at="{ row }">
        <span class="flex flex-wrap items-center gap-2">
          <bdi class="tabular-nums">{{ time(row.accepted_at) }}</bdi>
          <UiBadge
            v-if="row.voided"
            tone="neutral"
            size="sm"
          >
            {{ t('offers.mine.voided') }}
          </UiBadge>
        </span>
      </template>
      <template #cell-amount_minor="{ row }">
        <UiAmount
          :minor="row.amount_minor"
          :class="row.voided && 'text-fg-muted line-through'"
        />
      </template>
      <template #cell-stage="{ row }">
        {{ t(`offers.stage.${row.stage}`) }}
      </template>
      <template #cell-change="{ row }">
        <span
          v-if="row.changeBps === null"
          class="text-fg-muted"
        >{{ row.voided ? '—' : t('offers.mine.first') }}</span>
        <span
          v-else-if="row.changeBps === 0"
          class="text-fg-muted"
        >{{ t('offers.mine.change_none') }}</span>
        <span
          v-else
          class="inline-flex items-center gap-1 text-fg-muted tabular-nums"
        >
          <span aria-hidden="true">{{ row.changeBps < 0 ? '↓' : '↑' }}</span>
          <i18n-t
            :keypath="row.changeBps < 0 ? 'offers.mine.change_lower' : 'offers.mine.change_higher'"
            scope="global"
          >
            <template #pct>
              <bdi>{{ formatBpsPercent(row.changeBps) }}</bdi>
            </template>
          </i18n-t>
        </span>
      </template>
    </UiTable>
  </div>
</template>
