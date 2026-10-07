<script setup lang="ts">
import { CircleSlash, Gavel, Layers } from '@lucide/vue'
import { fetchAward, fetchStandings, issueAward } from '~/services/bidding'
import { closeCompetitionWithoutAward } from '~/services/competitions'
import type { Award, ParticipantStandingRow } from '~/types/api/bidding'
import type { CloseReasonRequest, IssuerCompetition } from '~/types/api/competitions'
import { awardHints, isIssuerSnapshot, rowsWithOffers } from '~/stores/competition-editor-console'

/**
 * W22 Evaluation and award (issuer) (SCREENS §2.4, ARCHITECTURE §7.11–§7.12), from `closed`:
 *
 * - `closed`: final standings with a radio per participant with an offer; a justification (reason of
 *   kind `award_justification`, a note when required) when the choice is not rank 1 or misses the
 *   reserve, plus the reserve confirmation; message to the winner and internal notes; **Award**
 *   through a confirmation; **Start BAFO round** (shortlist) and **Close without award**;
 * - `bafo_round`: the round card; award and close wait until the round ends;
 * - `awarded`: the award detail with **Revoke award**; `not_awarded`: the reason;
 * - the result report (AR/EN) and, with `integrations.manage`, the results export.
 *
 * The server decides every rule: its errors (`award_justification_required`,
 * `award_reserve_confirmation_required`, …) reveal the matching fields. No optimistic state.
 */
const ctx = useCompetitionContext()
const { t } = useI18n()
const toast = useToast()
const auth = useAuthStore()
const features = useFeatures()
const lookups = useLookupsStore()
const money = useMoney()
const { message } = useErrorMessage()

const competition = computed(() => (ctx.competition.value?.viewer_role === 'issuer' ? ctx.competition.value as IssuerCompetition : null))
const status = computed(() => ctx.live.value?.status ?? competition.value?.status ?? null)
const permissions = computed(() => competition.value?.permissions)
const snapshot = computed(() => (isIssuerSnapshot(ctx.live.value) ? ctx.live.value : null))

const standings = ref<ParticipantStandingRow[]>([])
const award = ref<Award | null>(null)
const loading = ref(true)
const loadError = ref<unknown>(null)

async function load(): Promise<void> {
  const id = competition.value?.id
  if (!id) return
  loadError.value = null
  try {
    const [rows, current] = await Promise.all([fetchStandings(id), fetchAward<Award>(id)])
    standings.value = rows
    award.value = current
  }
  catch (error) {
    loadError.value = error
  }
  finally {
    loading.value = false
  }
}

onMounted(() => {
  void load()
  void lookups.ensureLoaded().catch(() => {})
})
watch(status, (next, previous) => {
  if (previous && next !== previous) void load()
})

// ---------- Award form ----------

const selectedId = ref<string | null>(null)
const reasonId = ref<string | null>(null)
const justificationText = ref('')
const confirmReserve = ref(false)
const messageToWinner = ref('')
const internalNotes = ref('')
const serverNeeds = ref<{ justification: boolean, reserve: boolean }>({ justification: false, reserve: false })
const formError = ref<string | null>(null)
const attempted = ref(false)
const confirmOpen = ref(false)
const awarding = ref(false)

const selected = computed(() => standings.value.find(row => row.participant.id === selectedId.value) ?? null)
const hints = computed(() => awardHints(selected.value, competition.value?.rules.reserve_price_minor ?? null, competition.value?.direction ?? 'tender'))
const needsJustification = computed(() => hints.value.notLeading || hints.value.reserveNotMet || serverNeeds.value.justification || serverNeeds.value.reserve)
const needsReserveConfirm = computed(() => hints.value.reserveNotMet || serverNeeds.value.reserve)
const justificationReasons = computed(() => lookups.closeReasonsOf('award_justification'))
const reason = computed(() => justificationReasons.value.find(item => item.id === reasonId.value) ?? null)
const reasonOptions = computed(() => justificationReasons.value.map(item => ({ value: item.id, label: item.name })))

watch(selectedId, () => {
  serverNeeds.value = { justification: false, reserve: false }
  formError.value = null
})

const reasonError = computed(() => (attempted.value && needsJustification.value && !reasonId.value ? t('award.workspace.reason_required') : null))
const textError = computed(() => (attempted.value && needsJustification.value && reason.value?.requires_note && !justificationText.value.trim() ? t('award.workspace.text_required') : null))
const reserveError = computed(() => (attempted.value && needsReserveConfirm.value && !confirmReserve.value ? t('award.workspace.reserve_required') : null))

function openConfirm(): void {
  attempted.value = true
  formError.value = null
  if (!selected.value) {
    formError.value = t('award.workspace.select_required')
    return
  }
  if (reasonError.value || textError.value || reserveError.value) return
  confirmOpen.value = true
}

async function submitAward(): Promise<void> {
  const id = competition.value?.id
  if (!id || !selected.value) return
  awarding.value = true
  try {
    await issueAward(id, {
      participant_id: selected.value.participant.id,
      justification_reason_id: needsJustification.value ? reasonId.value : null,
      justification_text: needsJustification.value ? justificationText.value.trim() || null : null,
      confirm_reserve_not_met: needsReserveConfirm.value ? confirmReserve.value : false,
      message_to_winner: messageToWinner.value.trim() || null,
      internal_notes: internalNotes.value.trim() || null,
    })
    confirmOpen.value = false
    toast.success(t('award.workspace.done'))
    await ctx.refetch()
    await load()
  }
  catch (error) {
    confirmOpen.value = false
    if (error instanceof ApiError && error.code === 'award_justification_required') {
      serverNeeds.value = { ...serverNeeds.value, justification: true, reserve: serverNeeds.value.reserve || error.detailString('reason') === 'reserve_not_met' }
    }
    else if (error instanceof ApiError && error.code === 'award_reserve_confirmation_required') {
      serverNeeds.value = { justification: true, reserve: true }
    }
    else if (error instanceof ApiError && error.code === 'invalid_state_transition') {
      void ctx.refetch()
    }
    formError.value = message(error)
  }
  finally {
    awarding.value = false
  }
}

// ---------- BAFO round and close without award ----------

const shortlistOpen = ref(false)
const closeOpen = ref(false)

function onBafoStarted(updated: IssuerCompetition): void {
  ctx.competition.value = updated
  toast.success(t('bafo.shortlist.done'))
  void ctx.resync()
}

async function submitClose(body: CloseReasonRequest): Promise<void> {
  if (!competition.value) return
  ctx.competition.value = await closeCompetitionWithoutAward(competition.value.id, body)
  toast.success(t('award.close.done'))
  void ctx.resync()
}

async function onRevoked(): Promise<void> {
  selectedId.value = null
  await ctx.refetch()
  await load()
}

const reportAvailable = computed(() => Boolean(competition.value?.schedule.closed_at) || ['closed', 'bafo_round', 'awarded', 'not_awarded'].includes(status.value ?? ''))
const bafoCutoff = computed(() => snapshot.value?.bafo?.cutoff_at ?? competition.value?.bafo_round?.cutoff_at ?? null)
</script>

<template>
  <div
    v-if="competition"
    class="flex flex-col gap-6"
  >
    <UiAlert
      v-if="status && !['closed', 'bafo_round', 'awarded', 'not_awarded'].includes(status)"
      tone="info"
    >
      {{ t('award.workspace.not_yet') }}
    </UiAlert>

    <template v-else>
      <!-- BAFO round running -->
      <template v-if="status === 'bafo_round'">
        <CompetitionsIssuerBafoRoundCard
          :cutoff-at="bafoCutoff"
          :shortlist-count="snapshot?.bafo?.shortlist_count ?? competition.bafo_round?.shortlist_count ?? 0"
          :submitted-count="snapshot?.bafo?.submitted_count ?? competition.bafo_round?.submitted_count ?? 0"
        />
        <UiAlert tone="info">
          {{ t('award.workspace.bafo_wait') }}
        </UiAlert>
      </template>

      <!-- Awarded -->
      <CompetitionsIssuerAwardDetail
        v-if="status === 'awarded' && award"
        :competition-id="competition.id"
        :direction="competition.direction"
        :award="award"
        :can-revoke="Boolean(permissions?.can_revoke_award)"
        @revoked="onRevoked"
      />

      <!-- Closed without award -->
      <UiAlert
        v-if="status === 'not_awarded' && competition.not_awarded"
        tone="info"
        :icon="CircleSlash"
        :title="t('competitions.issuer.overview.not_awarded_title')"
      >
        <p>{{ competition.not_awarded.reason.name }}</p>
        <p v-if="competition.not_awarded.note">
          {{ competition.not_awarded.note }}
        </p>
      </UiAlert>

      <!-- Evaluation -->
      <template v-if="status === 'closed'">
        <UiAlert
          v-if="award && award.status === 'revoked'"
          tone="warning"
        >
          {{ t('award.workspace.previous_revoked', { reason: award.revoke_reason ?? '' }) }}
        </UiAlert>
        <div class="flex flex-wrap items-center justify-between gap-3">
          <p class="max-w-2xl text-sm text-fg-muted">
            {{ t('award.workspace.intro') }}
          </p>
          <div class="flex flex-wrap gap-2">
            <UiButton
              v-if="permissions?.can_start_bafo && features.enabled('bafo_round')"
              variant="secondary"
              :icon="Layers"
              @click="shortlistOpen = true"
            >
              {{ t('bafo.shortlist.open') }}
            </UiButton>
            <UiButton
              v-if="permissions?.can_close_without_award"
              variant="danger-ghost"
              :icon="CircleSlash"
              @click="closeOpen = true"
            >
              {{ t('award.close.open') }}
            </UiButton>
          </div>
        </div>
      </template>

      <section class="flex flex-col gap-3">
        <h2 class="text-base font-bold text-fg">
          {{ t('award.workspace.standings') }}
        </h2>
        <UiCard
          v-if="loadError && standings.length === 0"
          padding="none"
        >
          <UiErrorState
            :error="loadError"
            @retry="load"
          />
        </UiCard>
        <CompetitionsIssuerStandingsTable
          v-else
          v-model:selected="selectedId"
          :rows="standings"
          :direction="competition.direction"
          :loading="loading"
          :selectable="status === 'closed' && Boolean(permissions?.can_award)"
          :show-contact="false"
        />
        <p class="text-xs text-fg-muted">
          {{ t('common.prices_exclude_vat') }}
        </p>
      </section>

      <UiCard
        v-if="status === 'closed' && permissions?.can_award"
        :title="t('award.workspace.form_title')"
      >
        <form
          class="flex flex-col gap-5"
          novalidate
          @submit.prevent="openConfirm"
        >
          <p
            v-if="selected"
            class="text-sm text-fg"
          >
            {{ t('award.workspace.selected', { alias: selected.participant.alias_no, name: selected.participant.organization.name }) }}
            <UiAmount
              :minor="selected.current_amount_minor"
              class="font-bold"
            />
          </p>
          <template v-if="needsJustification">
            <UiAlert tone="warning">
              {{ hints.reserveNotMet || serverNeeds.reserve ? t('award.workspace.why_reserve') : t('award.workspace.why_not_leading') }}
            </UiAlert>
            <UiSelect
              v-model="reasonId"
              :options="reasonOptions"
              :label="t('award.workspace.reason')"
              :placeholder="t('common.select_placeholder')"
              :error="reasonError"
              required
            />
            <UiTextarea
              v-model="justificationText"
              :label="t('award.workspace.justification_text')"
              :hint="reason?.requires_note ? t('competitions.issuer.reason.note_required_hint') : t('common.optional')"
              :error="textError"
              :required="reason?.requires_note"
              :maxlength="2000"
              :rows="3"
            />
            <UiCheckbox
              v-if="needsReserveConfirm"
              v-model="confirmReserve"
              :label="t(`award.workspace.confirm_reserve.${competition.direction}`)"
              :error="reserveError"
              required
            />
          </template>
          <UiTextarea
            v-model="messageToWinner"
            :label="t('award.workspace.message')"
            :hint="t('award.workspace.message_hint')"
            :maxlength="2000"
            :rows="3"
          />
          <UiTextarea
            v-model="internalNotes"
            :label="t('award.workspace.notes')"
            :hint="t('award.workspace.notes_hint')"
            :maxlength="5000"
            :rows="3"
          />
          <UiAlert
            v-if="formError"
            tone="danger"
          >
            {{ formError }}
          </UiAlert>
          <div class="flex justify-end">
            <UiButton
              type="submit"
              :icon="Gavel"
              :disabled="!selected"
            >
              {{ t('award.workspace.award') }}
            </UiButton>
          </div>
        </form>
      </UiCard>

      <div class="grid gap-4 md:grid-cols-2">
        <CompetitionsIssuerReportButton
          v-if="reportAvailable"
          :competition-id="competition.id"
        />
        <UiCard
          v-if="auth.can('integrations.manage') && reportAvailable && features.enabled('csv_import_export')"
          :title="t('competitions.issuer.export.results_title')"
          padding="sm"
        >
          <CompetitionsIssuerExportButton
            :competition-id="competition.id"
            type="results"
          />
        </UiCard>
      </div>
    </template>

    <UiConfirmDialog
      v-model:open="confirmOpen"
      :title="t('award.confirm.title')"
      :confirm-label="t('award.confirm.confirm')"
      :busy="awarding"
      @confirm="submitAward"
    >
      <dl
        v-if="selected"
        class="flex flex-col gap-2 text-sm"
      >
        <div class="flex justify-between gap-3">
          <dt class="text-fg-muted">
            {{ t('award.detail.winner') }}
          </dt>
          <dd class="text-end text-fg">
            {{ t('offers.participant_alias', { number: selected.participant.alias_no }) }} · {{ selected.participant.organization.name }}
          </dd>
        </div>
        <div class="flex justify-between gap-3">
          <dt class="text-fg-muted">
            {{ t('award.detail.amount') }}
          </dt>
          <dd>
            <bdi class="font-bold tabular-nums">{{ selected.current_amount_minor !== null ? money.format(selected.current_amount_minor) : '—' }}</bdi>
          </dd>
        </div>
        <div
          v-if="needsJustification && reason"
          class="flex justify-between gap-3"
        >
          <dt class="text-fg-muted">
            {{ t('award.detail.justification') }}
          </dt>
          <dd class="text-end text-fg">
            {{ reason.name }}
          </dd>
        </div>
      </dl>
      <p class="mt-3 text-sm text-fg-muted">
        {{ t('award.confirm.body') }}
      </p>
    </UiConfirmDialog>

    <CompetitionsIssuerBafoShortlistDialog
      v-model:open="shortlistOpen"
      :competition="competition"
      :rows="rowsWithOffers(standings)"
      @started="onBafoStarted"
    />
    <CompetitionsIssuerReasonDialog
      v-model:open="closeOpen"
      kind="not_awarded"
      :title="t('award.close.title')"
      :description="t('award.close.description')"
      :confirm-label="t('award.close.confirm')"
      :submit="submitClose"
    />
  </div>
</template>
