<script setup lang="ts">
import { BookOpenText, Radio } from '@lucide/vue'
import type { ExtensionNotice } from '~/stores/liveRules'
import type { SubmitOfferResult } from '~/types/api/bidding'
import type { ParticipantCompetition } from '~/types/api/competitions'

/**
 * W19 participant live room (SCREENS §2.4, S3–S5, S9). Mount it inside the competition detail
 * parent, which calls `provideCompetition(id)`: this component reads the participant projection
 * and the `v`-ordered `ParticipantLiveSnapshot` from the context (channel
 * `private-competition.{id}.participant.{orgId}`, subscribe-buffer-fetch resync, polling fallback).
 *
 * Everything shown comes from the server: standing, bounds, visible competitor prices (only when
 * the projection carries them) and the result. Offers go through the confirm step with one
 * idempotency key per intent; nothing changes before the server answers (CD9). Extensions, phase
 * changes and the close are announced through live regions (polite; the close is assertive).
 */
const ctx = useCompetitionContext()
const store = useLiveRoomStore()
const { t } = useI18n()
const toast = useToast()
const date = useDate()
const clock = useServerTime()

const competition = computed(() => (ctx.competition.value?.viewer_role === 'participant' ? ctx.competition.value as ParticipantCompetition : null))
const snapshot = computed(() => (isParticipantSnapshot(ctx.live.value) ? ctx.live.value : null))
const status = computed(() => snapshot.value?.status ?? competition.value?.status ?? null)
const countdown = computed(() => (snapshot.value ? liveCountdownOf(snapshot.value) : null))
const timer = useCountdown(() => countdown.value?.target, { keepSynced: false })

/** The clock reached zero but the server has not closed yet: «جارٍ الإغلاق…» (S3 step 9). */
const closing = computed(() => timer.expired.value && countdown.value !== null && countdown.value.kind !== 'opens')
const accepting = computed(() => Boolean(snapshot.value?.accepting_offers) && !closing.value)
const ended = computed(() => status.value === 'closed' || status.value === 'awarded' || status.value === 'not_awarded' || status.value === 'cancelled')
const pollSeconds = computed(() => Math.round(pollIntervalMs(timer.remainingMs.value) / 1000))
const detailPath = computed(() => (competition.value ? `/dashboard/competitions/${competition.value.id}` : null))
const rulesOpen = ref(false)

const showStanding = computed(() => status.value === 'live' || status.value === 'closed')
const showSealed = computed(() => competition.value?.format === 'sealed' && status.value === 'live')
const showBafo = computed(() => Boolean(snapshot.value?.bafo) && (status.value === 'bafo_round' || status.value === 'closed'))
const result = computed(() => snapshot.value?.result ?? competition.value?.result ?? null)

// ---------- Presence and history ----------

useLiveHeartbeat(() => competition.value?.id, () => competition.value !== null && isRunningStatus(status.value))

watch(() => competition.value?.id, (id) => {
  if (id && (store.competitionId !== id || !store.loaded)) void store.load(id)
}, { immediate: true })

ctx.on('liveApplied', (applied) => {
  const id = competition.value?.id
  if (id && isParticipantSnapshot(applied)) store.syncWithSnapshot(id, applied)
})

// ---------- Announcements (S9) ----------

const politeMessage = ref('')
const assertiveMessage = ref('')
const extensionNotice = ref<string | null>(null)

function extensionText(notice: ExtensionNotice): string {
  if (notice.reason === 'manual' || notice.reason === 'admin') {
    return t('live.extended_manual', { time: date.formatDeadline(notice.closeAt) })
  }
  return t('live.extended', { minutes: t('live.minutes', { count: notice.minutes }, notice.minutes) })
}

watch(snapshot, (next, previous) => {
  if (!next || !previous || next.competition_id !== previous.competition_id) return
  const extension = detectExtension(previous, next)
  if (extension) {
    const text = extensionText(extension)
    extensionNotice.value = text
    politeMessage.value = text
    // A manual extension during the initial phase also moves the final window (ARCHITECTURE §7.17).
    if (next.phase === 'initial') void ctx.refetch()
  }
  const transition = detectTransition(previous, next)
  if (transition) {
    const text = t(`live.announce.${transition}`)
    if (transition === 'closed' || transition === 'cancelled' || transition === 'awarded' || transition === 'not_awarded') {
      assertiveMessage.value = text
      extensionNotice.value = null
    }
    else {
      politeMessage.value = text
    }
  }
})

// ---------- Offers ----------

function onAccepted(response: SubmitOfferResult): void {
  const current = competition.value
  if (!current) return
  ctx.applyLive(response.live)
  store.recordAccepted(current.id, response.offer)
  const time = t('offers.machine_time', { time: formatMachineTime(response.offer.accepted_at, { date: false }) ?? '' })
  toast.success(response.offer.stage === 'bafo' ? t('offers.submitted_bafo', { time }) : t('offers.submitted', { time }))
}

function onStale(): void {
  void ctx.refetch()
  void ctx.resync()
}

const historyTo = computed(() => (detailPath.value ? { path: `${detailPath.value}/my-offers` } : null))
</script>

<template>
  <div
    class="flex flex-col gap-4"
    data-testid="participant-live-room"
  >
    <div
      v-if="ctx.loading.value && !ctx.competition.value"
      class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_20rem]"
      aria-busy="true"
      :aria-label="t('common.loading')"
    >
      <UiSkeleton class="h-48" />
      <UiSkeleton class="h-48" />
    </div>

    <UiErrorState
      v-else-if="ctx.error.value && !ctx.competition.value"
      :error="ctx.error.value"
      @retry="ctx.refetch"
    />

    <UiEmptyState
      v-else-if="ctx.viewerRole.value === 'invitee'"
      :icon="Radio"
      :title="t('live.room.invitee_title')"
      :description="t('live.room.invitee_body')"
    >
      <UiButton
        v-if="detailPath"
        :to="detailPath"
        variant="secondary"
      >
        {{ t('live.room.back_to_overview') }}
      </UiButton>
    </UiEmptyState>

    <template v-else-if="competition">
      <div
        v-if="!snapshot"
        class="flex flex-col gap-4"
        aria-busy="true"
      >
        <p class="text-sm text-fg-muted">
          {{ t('live.room.no_snapshot') }}
        </p>
        <UiSkeleton class="h-40" />
      </div>

      <template v-else>
        <LiveConnectionBanner
          :state="ctx.connection.value"
          :poll-seconds="pollSeconds"
        />
        <UiAlert
          v-if="extensionNotice"
          tone="info"
          dismissible
          data-testid="extension-notice"
          @dismiss="extensionNotice = null"
        >
          {{ extensionNotice }}
        </UiAlert>

        <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_20rem] xl:items-start">
          <div class="flex min-w-0 flex-col gap-4">
            <LiveCountdownPanel
              :snapshot="snapshot"
              :connection="ctx.connection.value"
              :final-window-starts-at="competition.schedule.final_window_starts_at"
              :closed-at="competition.schedule.closed_at"
              :remaining-ms="timer.remainingMs.value"
              :slow="clock.slow.value"
            />

            <LiveBafoBanner
              v-if="showBafo && snapshot.bafo"
              :bafo="snapshot.bafo"
              :direction="snapshot.direction"
              :status="snapshot.status"
            />

            <LiveStandingBanner
              v-if="showStanding"
              :snapshot="snapshot"
            />

            <LiveSealedLockPanel
              v-if="showSealed"
              :offer="snapshot.my_offer"
            />

            <LiveOfferComposer
              v-if="accepting"
              :competition-id="competition.id"
              :snapshot="snapshot"
              :can-submit="ctx.canSubmit.value"
              :connection="ctx.connection.value"
              :closing="closing"
              @accepted="onAccepted"
              @stale="onStale"
            />
            <UiCard
              v-else-if="status === 'scheduled' && snapshot.bidding_opens_at"
              padding="sm"
              data-testid="composer-unavailable"
            >
              <p class="flex flex-wrap items-center gap-2 text-sm text-fg">
                <span>{{ t('live.countdown.opens') }}</span>
                <UiCountdown
                  :ends-at="snapshot.bidding_opens_at"
                  :label="t('live.countdown.opens')"
                  :ended-label="t('live.countdown.opening')"
                  size="sm"
                />
              </p>
            </UiCard>
            <UiCard
              v-else-if="status === 'live'"
              padding="sm"
              data-testid="composer-unavailable"
            >
              <p
                class="text-sm text-fg"
                role="status"
              >
                {{ closing ? t('live.countdown.closing') : t('live.composer.unavailable.generic') }}
              </p>
            </UiCard>

            <LiveResultPanel
              v-if="ended && status"
              :competition-id="competition.id"
              :status="status"
              :result="result"
              :my-offer="snapshot.my_offer"
              :rank="snapshot.rank"
              :ranked-count="snapshot.ranked_count"
              :is-leading="snapshot.is_leading"
              :cancellation="competition.cancellation"
              :not-awarded="competition.not_awarded"
            />
          </div>

          <aside
            class="flex min-w-0 flex-col gap-4"
            :aria-label="t('live.room.aside_label')"
          >
            <LiveMyOfferCard
              :offer="snapshot.my_offer"
              :offers-count="snapshot.my_offers_count"
              :history-to="historyTo"
            />
            <LiveLadder
              :leading-amount-minor="snapshot.leading_amount_minor"
              :ladder="snapshot.ladder"
            />
            <UiButton
              variant="secondary"
              :icon="BookOpenText"
              block
              @click="rulesOpen = true"
            >
              {{ t('live.room.rules_open') }}
            </UiButton>
          </aside>
        </div>

        <LiveRulesDrawer
          v-model:open="rulesOpen"
          :lines="competition.rules_summary"
        />
      </template>

      <p
        class="sr-only"
        aria-live="polite"
        aria-atomic="true"
        data-testid="room-announcer-polite"
      >
        {{ politeMessage }}
      </p>
      <p
        class="sr-only"
        aria-live="assertive"
        aria-atomic="true"
        data-testid="room-announcer-assertive"
      >
        {{ assertiveMessage }}
      </p>
    </template>
  </div>
</template>
