<script setup lang="ts">
import { Check, Lock, Minus, Trophy } from '@lucide/vue'
import type { RankingRow } from '~/types/api/bidding'
import type { TableColumn } from '~/types/ui'

/**
 * Issuer ranking (SCREENS W19 `RankingTable`) exactly as the `IssuerLiveSnapshot` carries it: rank,
 * alias and organisation, current and first amounts, offers, last offer time and BAFO flags. While a
 * sealed competition is not unlocked the server sends amounts, ranks and the leader as `null`: the
 * table shows «مغلق» and a Submitted / Not yet column instead. Nothing is ranked on the client.
 */
const props = defineProps<{
  rows: RankingRow[]
  /** Sealed and not yet unlocked: show the submitted column, hide amounts. */
  locked: boolean
  bafo: boolean
  /** Participant ids whose row changed in the last snapshot (300 ms highlight). */
  changed?: ReadonlySet<string>
}>()

const { t } = useI18n()

const columns = computed<TableColumn[]>(() => {
  const list: TableColumn[] = [
    { key: 'rank', label: t('live.console.ranking.rank'), numeric: true },
    { key: 'participant', label: t('live.console.ranking.participant'), primary: true, class: 'min-w-44' },
  ]
  if (props.locked) {
    list.push({ key: 'submitted', label: t('live.console.ranking.submitted') })
  }
  else {
    list.push(
      { key: 'current', label: t('live.console.ranking.current'), numeric: true },
      { key: 'first', label: t('live.console.ranking.first'), numeric: true, hideOnMobile: true },
    )
  }
  list.push(
    { key: 'offers', label: t('live.console.ranking.offers'), numeric: true },
    { key: 'last', label: t('live.console.ranking.last_offer') },
  )
  if (props.bafo) list.push({ key: 'bafo', label: t('live.console.ranking.bafo') })
  return list
})

const asRow = (row: unknown) => row as RankingRow
</script>

<template>
  <UiTable
    :columns="columns"
    :rows="rows"
    row-key="participant_id"
    :caption="t('live.console.ranking.caption')"
    :empty-title="t('live.console.ranking.empty')"
  >
    <template #cell-rank="{ row }">
      <span class="inline-flex items-center gap-1 tabular-nums">
        <Trophy
          v-if="asRow(row).is_leader"
          :size="14"
          class="text-brand"
          :aria-label="t('glossary.leading_offer')"
        />
        {{ asRow(row).rank ?? '—' }}
      </span>
    </template>
    <template #cell-participant="{ row }">
      <span
        class="flex min-w-0 flex-col rounded-sm transition-colors duration-300 motion-reduce:transition-none"
        :class="changed?.has(asRow(row).participant_id) && 'bg-primary-soft'"
      >
        <span class="font-semibold text-fg">{{ t('offers.participant_alias', { number: asRow(row).alias_no }) }}</span>
        <span class="truncate text-xs text-fg-muted">{{ asRow(row).organization.name }}</span>
      </span>
    </template>
    <template #cell-submitted="{ row }">
      <UiBadge
        :tone="asRow(row).submitted ? 'primary' : 'neutral'"
        :icon="asRow(row).submitted ? Check : Minus"
        size="sm"
      >
        {{ asRow(row).submitted ? t('live.console.ranking.submitted_yes') : t('live.console.ranking.submitted_no') }}
      </UiBadge>
    </template>
    <template #cell-current="{ row }">
      <UiAmount
        v-if="asRow(row).current_amount_minor !== null"
        :minor="asRow(row).current_amount_minor"
      />
      <span
        v-else-if="locked"
        class="inline-flex items-center gap-1 text-fg-muted"
      ><Lock
        :size="14"
        aria-hidden="true"
      />{{ t('offers.sealed_amount') }}</span>
      <span v-else>—</span>
    </template>
    <template #cell-first="{ row }">
      <UiAmount :minor="asRow(row).first_amount_minor" />
    </template>
    <template #cell-offers="{ row }">
      {{ asRow(row).offers_count }}
    </template>
    <template #cell-last="{ row }">
      <UiDateTime
        v-if="asRow(row).last_offer_at"
        :value="asRow(row).last_offer_at"
        format="time"
      />
      <span v-else>—</span>
    </template>
    <template #cell-bafo="{ row }">
      <span class="flex flex-wrap gap-1">
        <UiBadge
          v-if="asRow(row).bafo.shortlisted"
          tone="neutral"
          solid
          size="sm"
        >
          {{ t('bafo.round.shortlisted') }}
        </UiBadge>
        <UiBadge
          v-if="asRow(row).bafo.submitted"
          tone="primary"
          size="sm"
        >
          {{ t('bafo.round.submitted') }}
        </UiBadge>
        <span v-if="!asRow(row).bafo.shortlisted && !asRow(row).bafo.submitted">—</span>
      </span>
    </template>
  </UiTable>
</template>
