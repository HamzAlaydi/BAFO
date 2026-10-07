<script setup lang="ts">
import { ArrowDown, ArrowUp, Lock, Trophy } from '@lucide/vue'
import type { ParticipantStandingRow } from '~/types/api/bidding'
import type { Direction } from '~/types/api/competitions'
import type { TableColumn } from '~/types/ui'

/**
 * Standings per participant (SCREENS W20 per-participant view, W22 `AwardCandidateTable`) from
 * `GET …/offers`, in the server's rank order: rank, alias and organisation (CR), current and first
 * amounts, the change ratio (server-signed "Improvement", neutral colour with the numeric arrow of the
 * direction), offers, last offer, contact and coverage. With `selectable`, participants with a current
 * offer get a radio (award) — rows the server rejected are marked. `UiTable` renders the rows in two
 * layouts, so each layout has its own radio group (one shared group would uncheck the visible radio).
 */
const props = withDefaults(defineProps<{
  rows: ParticipantStandingRow[]
  direction: Direction
  loading?: boolean
  selectable?: boolean
  showContact?: boolean
  invalidIds?: readonly string[]
}>(), {
  loading: false,
  selectable: false,
  showContact: true,
  invalidIds: () => [],
})

const selected = defineModel<string | null>('selected', { default: null })
const { t } = useI18n()
const features = useFeatures()
const name = `standing-${useId()}`

const columns = computed<TableColumn[]>(() => {
  const list: TableColumn[] = []
  if (props.selectable) list.push({ key: 'select', label: t('award.workspace.select') })
  list.push(
    { key: 'rank', label: t('live.console.ranking.rank'), numeric: true },
    { key: 'participant', label: t('live.console.ranking.participant'), primary: true, class: 'min-w-44' },
    { key: 'current', label: t('live.console.ranking.current'), numeric: true },
    { key: 'first', label: t('live.console.ranking.first'), numeric: true, hideOnMobile: true },
    { key: 'change', label: t('offers.standings.improvement'), numeric: true },
    { key: 'offers', label: t('live.console.ranking.offers'), numeric: true },
  )
  if (props.rows.some(row => row.bafo.shortlisted)) list.push({ key: 'bafo', label: t('live.console.ranking.bafo') })
  if (props.showContact) list.push({ key: 'contact', label: t('offers.standings.contact'), hideOnMobile: true })
  // Fee coverage belongs to sponsorship: shown with the flag, or when a covered pass exists (existing records).
  if (features.enabled('sponsorship') || props.rows.some(row => row.participant.coverage === 'sponsored')) {
    list.push({ key: 'coverage', label: t('invitations.issuer.table.coverage'), hideOnMobile: true })
  }
  return list
})

/**
 * The numeric arrow shows how the price moved (never a colour): a positive, server-signed improvement
 * is a lower price in a tender and a higher one in an auction.
 */
function movementArrow(bps: number) {
  return (bps > 0) === (props.direction === 'auction') ? ArrowUp : ArrowDown
}

const hasOffer = (row: ParticipantStandingRow) => row.current_amount_minor !== null && row.offers_count > 0
const asRow = (row: unknown) => row as ParticipantStandingRow
</script>

<template>
  <UiTable
    :columns="columns"
    :rows="rows"
    :row-key="row => row.participant.id"
    :caption="t('offers.standings.caption')"
    stack-below="xl"
    :loading="loading"
    :empty-title="t('offers.standings.empty')"
  >
    <template #cell-select="{ row, layout }">
      <input
        v-if="hasOffer(asRow(row))"
        type="radio"
        class="size-[1.125rem] accent-primary"
        :name="`${name}-${layout}`"
        :value="asRow(row).participant.id"
        :checked="selected === asRow(row).participant.id"
        :aria-label="t('award.workspace.select_participant', { alias: asRow(row).participant.alias_no })"
        @change="selected = asRow(row).participant.id"
      >
      <span
        v-else
        class="text-xs text-fg-muted"
      >{{ t('award.workspace.no_offer') }}</span>
    </template>
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
        class="flex min-w-0 flex-col"
        :class="invalidIds.includes(asRow(row).participant.id) && 'rounded-sm outline-2 outline-danger'"
      >
        <span class="font-semibold text-fg">{{ t('offers.participant_alias', { number: asRow(row).participant.alias_no }) }}</span>
        <span class="truncate text-xs text-fg-muted">{{ asRow(row).participant.organization.name }}</span>
        <span
          v-if="asRow(row).participant.organization.cr_number"
          class="text-xs text-fg-muted"
        >{{ t('offers.standings.cr') }} <bdi>{{ asRow(row).participant.organization.cr_number }}</bdi></span>
      </span>
    </template>
    <template #cell-current="{ row }">
      <UiAmount
        v-if="asRow(row).current_amount_minor !== null"
        :minor="asRow(row).current_amount_minor"
      />
      <span
        v-else-if="asRow(row).submitted"
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
    <template #cell-change="{ row }">
      <span
        v-if="asRow(row).change_ratio_bps !== null"
        class="inline-flex items-center gap-1 text-fg-muted"
      >
        <component
          :is="movementArrow(asRow(row).change_ratio_bps ?? 0)"
          v-if="asRow(row).change_ratio_bps"
          :size="14"
          aria-hidden="true"
        />
        <bdi class="tabular-nums">{{ formatBpsPercent(asRow(row).change_ratio_bps ?? 0, { signed: true }) }}</bdi>
      </span>
      <span v-else>—</span>
    </template>
    <template #cell-offers="{ row }">
      {{ asRow(row).offers_count }}
    </template>
    <template #cell-bafo="{ row }">
      <span class="flex flex-col gap-1">
        <UiBadge
          v-if="asRow(row).bafo.shortlisted"
          tone="neutral"
          solid
          size="sm"
        >
          {{ asRow(row).bafo.submitted ? t('bafo.round.submitted') : t('bafo.round.shortlisted') }}
        </UiBadge>
        <UiAmount
          v-if="asRow(row).bafo.reference_amount_minor !== null"
          :minor="asRow(row).bafo.reference_amount_minor"
          size="sm"
          class="text-fg-muted"
        />
      </span>
    </template>
    <template #cell-contact="{ row }">
      <span class="flex flex-col text-xs">
        <bdi v-if="asRow(row).participant.organization.email">{{ asRow(row).participant.organization.email }}</bdi>
        <bdi v-if="asRow(row).participant.organization.phone">{{ formatSaudiMobile(asRow(row).participant.organization.phone) }}</bdi>
      </span>
    </template>
    <template #cell-coverage="{ row }">
      <CompetitionsIssuerCoverageChip :coverage="asRow(row).participant.coverage" />
    </template>
  </UiTable>
</template>
