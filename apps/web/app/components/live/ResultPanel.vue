<script setup lang="ts">
import { Ban, ClipboardCheck, Flag, MessageSquareText, Trophy } from '@lucide/vue'
import { fetchAward } from '~/services/bidding'
import type { OwnOffer, ParticipantAwardView } from '~/types/api/bidding'
import type { CompetitionCancellation, CompetitionNotAwarded, CompetitionResult, CompetitionStatus } from '~/types/api/competitions'

/**
 * `ResultPanel` (SCREENS W14 participant, W19): the end of the competition for this participant,
 * rendered from the projection only (ARCHITECTURE §7.9 `result` table):
 * - `won` (with `winning_amount_minor` when published, and the issuer's message to the winner);
 * - `not_selected` (the winning amount only with `outcome_and_amount`);
 * - `not_awarded`, `null` (the issuer published nothing: the status only);
 * - evaluation (`closed`) and cancellation (with the reason).
 * "My final position" uses the participant's own final offer and, when projected, rank or flag.
 */
const props = defineProps<{
  competitionId: string
  status: CompetitionStatus
  result: CompetitionResult | null
  myOffer: OwnOffer | null
  rank?: number | null
  rankedCount?: number | null
  isLeading?: boolean | null
  cancellation?: CompetitionCancellation | null
  notAwarded?: CompetitionNotAwarded | null
}>()

const { t } = useI18n()
const outcome = computed(() => props.result?.outcome ?? null)
const ended = computed(() => props.status === 'awarded' || props.status === 'not_awarded')
const messageToWinner = ref<string | null>(null)

// The issuer's message is part of the winner's award view only (API.md §1.6 `GET …/award`).
watch([outcome, () => props.competitionId], async ([value, id]) => {
  messageToWinner.value = null
  if (value !== 'won' || !id) return
  try {
    const view = await fetchAward<ParticipantAwardView>(id)
    if (outcome.value === 'won' && props.competitionId === id) messageToWinner.value = view?.message_to_winner?.trim() || null
  }
  catch {
    // Optional detail: the result itself is already shown.
  }
}, { immediate: true })

const icon = computed(() => {
  if (props.status === 'cancelled') return Ban
  if (props.status === 'closed') return ClipboardCheck
  if (outcome.value === 'won') return Trophy
  return Flag
})

const body = computed(() => {
  if (props.status === 'cancelled') return t('award.result.cancelled')
  if (props.status === 'closed') return t('award.result.evaluation')
  switch (outcome.value) {
    case 'won': return t('award.result.won_body')
    case 'not_selected': return t('award.result.not_selected_body')
    case 'not_awarded': return t('award.result.not_awarded_body')
    default: return t('award.result.not_published')
  }
})

const reason = computed(() => {
  if (props.status === 'cancelled' && props.cancellation) return props.cancellation
  if (props.status === 'not_awarded' && props.notAwarded && outcome.value !== null) return props.notAwarded
  return null
})

const positionLine = computed(() => {
  if (!props.myOffer) return null
  if (props.rank !== null && props.rank !== undefined && props.rankedCount) return t('award.result.final_rank', { rank: props.rank, count: props.rankedCount })
  if (props.isLeading === true) return t('award.result.final_leading')
  if (props.isLeading === false) return t('award.result.final_not_leading')
  return null
})
</script>

<template>
  <section
    class="flex flex-col gap-4 rounded-lg border border-line bg-surface p-4 sm:p-5"
    data-testid="result-panel"
    :data-outcome="outcome ?? 'none'"
  >
    <div class="flex items-start gap-3">
      <span
        class="flex size-10 shrink-0 items-center justify-center rounded-full"
        :class="outcome === 'won' && ended ? 'bg-primary text-primary-fg' : 'bg-neutral-soft text-neutral-soft-fg'"
        aria-hidden="true"
      >
        <component
          :is="icon"
          :size="20"
        />
      </span>
      <div class="flex min-w-0 flex-col gap-2">
        <h3 class="font-bold text-fg">
          {{ t('award.result.title') }}
        </h3>
        <CompetitionsParticipantOutcomeChip
          v-if="ended && outcome"
          :outcome="outcome"
          size="sm"
          class="self-start"
        />
        <p class="text-sm text-fg">
          {{ body }}
        </p>
        <p
          v-if="ended && result?.winning_amount_minor !== null && result?.winning_amount_minor !== undefined"
          class="text-sm text-fg"
          data-testid="winning-amount"
        >
          <i18n-t
            keypath="award.result.amount"
            scope="global"
          >
            <template #amount>
              <UiAmount
                :minor="result.winning_amount_minor"
                class="font-bold"
              />
            </template>
          </i18n-t>
        </p>
        <p
          v-if="reason"
          class="text-sm text-fg-muted"
        >
          {{ t('award.result.reason', { reason: reason.reason.name }) }}<template v-if="reason.note">
            · {{ reason.note }}
          </template>
        </p>
      </div>
    </div>

    <div
      v-if="messageToWinner"
      class="flex items-start gap-2 rounded-md bg-surface-muted p-3 text-sm"
    >
      <MessageSquareText
        :size="16"
        class="mt-0.5 shrink-0 text-fg-muted"
        aria-hidden="true"
      />
      <div class="min-w-0">
        <p class="font-semibold text-fg">
          {{ t('award.result.message_title') }}
        </p>
        <p
          dir="auto"
          class="mt-0.5 whitespace-pre-line text-fg"
        >
          {{ messageToWinner }}
        </p>
      </div>
    </div>

    <dl
      v-if="status !== 'cancelled'"
      class="grid grid-cols-1 gap-2 border-t border-line pt-3 text-sm sm:grid-cols-2"
    >
      <div>
        <dt class="text-fg-muted">
          {{ t('award.result.final_offer') }}
        </dt>
        <dd class="font-semibold text-fg">
          <UiAmount
            v-if="myOffer"
            :minor="myOffer.amount_minor"
          />
          <span v-else>{{ t('award.result.no_offer') }}</span>
        </dd>
      </div>
      <div v-if="positionLine">
        <dt class="text-fg-muted">
          {{ t('award.result.final_position') }}
        </dt>
        <dd
          class="font-semibold text-fg"
          data-testid="final-position"
        >
          {{ positionLine }}
        </dd>
      </div>
    </dl>
  </section>
</template>
