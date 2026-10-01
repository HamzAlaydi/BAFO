<script setup lang="ts">
import { BadgeCheck, Radio } from '@lucide/vue'
import { listAttachments } from '~/services/competitions'
import type { Attachment, ParticipantCompetition } from '~/types/api/competitions'

/**
 * W14 overview for a **participant** (SCREENS §2.4; `viewer_role = participant`): the issuer card,
 * the participant's standing summary (from the live snapshot: S2 standing, current offer, required
 * next), the result panel once the competition ends, the description, the server's rules summary
 * (written for participants; the reserve price is never mentioned), the key dates, and the
 * competition documents and links. Mount it in the detail overview; it reads
 * `useCompetitionContext()` and refetches documents on `competition.updated` with `attachments`.
 */
const ctx = useCompetitionContext()
const { t } = useI18n()

const competition = computed(() => (ctx.competition.value?.viewer_role === 'participant' ? ctx.competition.value as ParticipantCompetition : null))
const snapshot = computed(() => (isParticipantSnapshot(ctx.live.value) ? ctx.live.value : null))
const status = computed(() => snapshot.value?.status ?? competition.value?.status ?? null)
const ended = computed(() => ['closed', 'awarded', 'not_awarded', 'cancelled'].includes(status.value ?? ''))
const showStanding = computed(() => snapshot.value !== null && (status.value === 'live' || status.value === 'closed' || status.value === 'bafo_round'))
const livePath = computed(() => (competition.value ? `/dashboard/competitions/${competition.value.id}/live` : null))

// ---------- Documents ----------

const attachments = ref<Attachment[]>([])
const attachmentsLoading = ref(false)
const attachmentsError = ref<unknown>(null)
let attachmentsSeq = 0

async function loadAttachments(): Promise<void> {
  const id = competition.value?.id
  if (!id) return
  const seq = ++attachmentsSeq
  attachmentsLoading.value = true
  try {
    const list = await listAttachments(id)
    if (seq !== attachmentsSeq) return
    attachments.value = list.filter(item => item.kind !== 'invitation_document')
    attachmentsError.value = null
  }
  catch (error) {
    if (seq === attachmentsSeq) attachmentsError.value = error
  }
  finally {
    if (seq === attachmentsSeq) attachmentsLoading.value = false
  }
}

watch(() => competition.value?.id, (id) => {
  attachments.value = []
  if (id) void loadAttachments()
}, { immediate: true })

ctx.on('competitionUpdated', (event) => {
  if (event.fields.includes('attachments')) void loadAttachments()
})

// ---------- Key dates ----------

interface DateRow {
  key: string
  label: string
  value: string
}

const dates = computed<DateRow[]>(() => {
  const current = competition.value
  if (!current) return []
  const schedule = current.schedule
  const close = snapshot.value?.effective_close_at ?? schedule.effective_close_at ?? schedule.scheduled_close_at
  const rows: Array<DateRow | null> = [
    schedule.bidding_opens_at ? { key: 'opens', label: t('competitions.participant.overview.dates.opens'), value: schedule.bidding_opens_at } : null,
    schedule.final_window_starts_at ? { key: 'final_window', label: t('competitions.participant.overview.dates.final_window'), value: schedule.final_window_starts_at } : null,
    close ? { key: 'closes', label: t('competitions.participant.overview.dates.closes'), value: close } : null,
    (snapshot.value?.hard_stop_at ?? schedule.hard_stop_at) ? { key: 'hard_stop', label: t('competitions.participant.overview.dates.hard_stop'), value: (snapshot.value?.hard_stop_at ?? schedule.hard_stop_at) as string } : null,
    current.bafo_round ? { key: 'bafo', label: t('competitions.participant.overview.dates.bafo_cutoff'), value: current.bafo_round.cutoff_at } : null,
    schedule.closed_at ? { key: 'closed', label: t('competitions.participant.overview.dates.closed'), value: schedule.closed_at } : null,
  ]
  return rows.filter((row): row is DateRow => row !== null)
})
</script>

<template>
  <div
    v-if="competition"
    class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_22rem] xl:items-start"
    data-testid="participant-overview"
  >
    <div class="flex min-w-0 flex-col gap-4">
      <UiCard
        v-if="showStanding && snapshot"
        :title="t('competitions.participant.overview.standing_title')"
      >
        <template
          v-if="livePath"
          #actions
        >
          <UiButton
            :to="livePath"
            size="sm"
            :icon="Radio"
          >
            {{ t('competitions.participant.overview.go_live') }}
          </UiButton>
        </template>
        <div class="flex flex-col gap-4">
          <LiveStandingBanner
            :snapshot="snapshot"
            :announce="false"
            compact
          />
          <dl class="grid grid-cols-1 gap-3 text-sm xs:grid-cols-2">
            <div>
              <dt class="text-fg-muted">
                {{ t('live.my_offer.title') }}
              </dt>
              <dd class="mt-0.5 font-semibold text-fg">
                <UiAmount
                  v-if="snapshot.my_offer"
                  :minor="snapshot.my_offer.amount_minor"
                />
                <span v-else>{{ t('live.my_offer.none') }}</span>
              </dd>
            </div>
            <div v-if="snapshot.required_next_amount_minor !== null && snapshot.accepting_offers">
              <dt class="text-fg-muted">
                {{ t('competitions.participant.overview.next_offer') }}
              </dt>
              <dd class="mt-0.5 font-semibold text-fg">
                <i18n-t
                  :keypath="`offers.hint.required_next.${snapshot.direction}`"
                  scope="global"
                >
                  <template #amount>
                    <UiAmount :minor="snapshot.required_next_amount_minor" />
                  </template>
                </i18n-t>
              </dd>
            </div>
          </dl>
        </div>
      </UiCard>

      <LiveResultPanel
        v-if="ended && status"
        :competition-id="competition.id"
        :status="status"
        :result="snapshot?.result ?? competition.result"
        :my-offer="snapshot?.my_offer ?? null"
        :rank="snapshot?.rank ?? null"
        :ranked-count="snapshot?.ranked_count ?? null"
        :is-leading="snapshot?.is_leading ?? null"
        :cancellation="competition.cancellation"
        :not-awarded="competition.not_awarded"
      />

      <UiCard
        v-if="competition.description"
        :title="t('competitions.participant.overview.description_title')"
      >
        <p
          dir="auto"
          class="text-sm leading-relaxed break-words whitespace-pre-line text-fg"
        >
          {{ competition.description }}
        </p>
      </UiCard>

      <UiCard
        v-if="competition.rules_summary.length > 0"
        :title="t('competitions.participant.overview.rules_title')"
      >
        <CompetitionsRulesSummary :lines="competition.rules_summary" />
      </UiCard>

      <CompetitionsParticipantAttachments
        :attachments="attachments"
        :title="t('competitions.participant.attachments.title')"
        :loading="attachmentsLoading"
        :error="attachmentsError"
        @retry="loadAttachments"
      />
    </div>

    <div class="flex min-w-0 flex-col gap-4">
      <UiCard :title="t('glossary.issuer')">
        <div class="flex items-center gap-3">
          <UiOrgLogo
            :name="competition.issuer.name"
            :src="competition.issuer.logo_url"
          />
          <p class="flex min-w-0 items-center gap-1 font-bold text-fg">
            <span class="truncate">{{ competition.issuer.name }}</span>
            <BadgeCheck
              v-if="competition.issuer.verified"
              :size="16"
              class="shrink-0 text-brand"
              role="img"
              :aria-label="t('organization.verified')"
            />
          </p>
        </div>
      </UiCard>

      <UiCard :title="t('competitions.participant.overview.participation_title')">
        <div class="flex flex-col gap-3 text-sm">
          <div class="flex flex-wrap items-center gap-2">
            <CompetitionsParticipantAccessChip
              :state="competition.access.state"
              size="sm"
            />
            <InvitationsFeesCoveredBadge
              v-if="competition.access.coverage === 'sponsored'"
              :sponsor-name="competition.access.sponsor_name"
              size="sm"
            />
          </div>
          <p class="text-fg-muted">
            <i18n-t
              keypath="competitions.participant.overview.joined_at"
              scope="global"
            >
              <template #time>
                <UiDateTime
                  :value="competition.participation.joined_at"
                  class="font-semibold text-fg"
                />
              </template>
            </i18n-t>
          </p>
        </div>
      </UiCard>

      <UiCard
        v-if="dates.length > 0"
        :title="t('competitions.participant.overview.dates_title')"
      >
        <dl class="flex flex-col gap-3 text-sm">
          <div
            v-for="row in dates"
            :key="row.key"
            class="flex flex-col gap-0.5"
          >
            <dt class="text-fg-muted">
              {{ row.label }}
            </dt>
            <dd class="font-semibold text-fg">
              <UiDateTime
                :value="row.value"
                format="deadline"
              />
            </dd>
          </div>
        </dl>
        <p class="mt-4 text-xs text-fg-muted">
          {{ t('competitions.participant.overview.dates_note') }}
        </p>
      </UiCard>
    </div>
  </div>
</template>
