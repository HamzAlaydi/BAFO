<script setup lang="ts">
import { CheckCircle2, CircleMinus, Radio, TrendingUp, Users } from '@lucide/vue'
import { fetchOfferLog } from '~/services/bidding'
import type { IssuerLiveSnapshot, OfferLogEntry } from '~/types/api/bidding'
import type { IssuerCompetition } from '~/types/api/competitions'
import { isIssuerSnapshot, lastOfferSeq, mergeOfferEntries, minutesBetween, offerSeqGap, OFFER_FEED_SIZE } from '~/stores/competition-editor-console'

/**
 * W19 issuer live console (SCREENS §2.4, S3, S4, S9). Everything comes from the `IssuerLiveSnapshot`
 * applied by the detail parent (private channel `competition.{id}`, subscribe-buffer-fetch resync,
 * `v` ordering, polling fallback):
 *
 * - header: status and phase, the countdown to the effective close with the latest possible close,
 *   the extension count and an extensions banner (auto, manual, admin), participants online, the
 *   connection state and its banner;
 * - leading offer card with the reserve indicator (issuer only) and the improvement against the start
 *   price (server-signed percentage, neutral colour);
 * - the ranking as projected (sealed and not unlocked: «مغلق» and a Submitted column);
 * - the latest accepted offers (`offer.accepted`, appended by `seq`, gaps filled from the log);
 * - activity metrics. The header's action bar (extend, cancel, invite) stays above the tabs.
 *
 * Leader changes are announced politely at most once every 10 s, lifecycle changes at once (the close,
 * award and cancellation assertively); values highlight for 300 ms (off under reduced motion). No
 * value is computed on the client.
 */
const ctx = useCompetitionContext()
const { t } = useI18n()
const date = useDate()
const money = useMoney()
const { td } = useDirectionCopy(() => ctx.competition.value?.direction)

const competition = computed(() => (ctx.competition.value?.viewer_role === 'issuer' ? ctx.competition.value as IssuerCompetition : null))
const snapshot = computed<IssuerLiveSnapshot | null>(() => (isIssuerSnapshot(ctx.live.value) ? ctx.live.value : null))
const status = computed(() => snapshot.value?.status ?? competition.value?.status ?? null)
const sealedLocked = computed(() => competition.value?.format === 'sealed' && !competition.value.schedule.offers_opened_at && (status.value === 'live' || status.value === 'scheduled'))
const base = computed(() => (competition.value ? `/dashboard/competitions/${competition.value.id}` : ''))

// ---------- Countdown and connection ----------

const countdownTarget = computed(() => {
  if (status.value === 'scheduled') return snapshot.value?.bidding_opens_at ?? competition.value?.schedule.bidding_opens_at ?? null
  if (status.value === 'live') return snapshot.value?.effective_close_at ?? competition.value?.schedule.effective_close_at ?? null
  if (status.value === 'bafo_round') return snapshot.value?.bafo?.cutoff_at ?? competition.value?.bafo_round?.cutoff_at ?? null
  return null
})
const countdownLabel = computed(() => {
  if (status.value === 'scheduled') return t('competitions.detail.countdown.opens')
  if (status.value === 'bafo_round') return t('competitions.detail.countdown.bafo_closes')
  return t('competitions.detail.countdown.closes')
})
const timer = useCountdown(countdownTarget, { keepSynced: true })
const pollSeconds = computed(() => Math.round(pollIntervalMs(timer.remainingMs.value) / 1000))
const connectionBanner = computed(() => ctx.connection.value === 'reconnecting' || ctx.connection.value === 'polling')

// ---------- Extensions banner ----------

interface ExtensionBanner {
  reason: 'auto' | 'manual' | 'admin'
  closeAt: string
  minutes: number | null
}
const extension = ref<ExtensionBanner | null>(null)

watch(snapshot, (next, previous) => {
  if (!next || !previous) return
  if (next.extension_count > previous.extension_count && next.effective_close_at) {
    const reason = next.last_change.kind === 'extension' && next.last_change.reason ? next.last_change.reason : 'manual'
    extension.value = { reason, closeAt: next.effective_close_at, minutes: minutesBetween(previous.effective_close_at, next.effective_close_at) }
  }
})

// ---------- Leader announcements and highlights ----------

const leaderAnnouncer = useThrottledAnnouncer()
const lifecycleMessage = ref('')
const closedMessage = ref('')
const changedRows = ref<Set<string>>(new Set())
let highlightTimer: ReturnType<typeof setTimeout> | null = null
const leaderFlash = ref(false)

watch(snapshot, (next, previous) => {
  if (!next || !previous) return
  const before = new Map(previous.ranking.map(row => [row.participant_id, `${row.rank}|${row.current_amount_minor}|${row.offers_count}`]))
  changedRows.value = new Set(next.ranking.filter(row => before.get(row.participant_id) !== `${row.rank}|${row.current_amount_minor}|${row.offers_count}`).map(row => row.participant_id))
  const leaderChanged = next.leader?.participant_id !== previous.leader?.participant_id || next.leader?.amount_minor !== previous.leader?.amount_minor
  leaderFlash.value = leaderChanged
  if (highlightTimer) clearTimeout(highlightTimer)
  highlightTimer = setTimeout(() => {
    changedRows.value = new Set()
    leaderFlash.value = false
  }, 300)
  if (leaderChanged && next.leader) {
    leaderAnnouncer.announce(t('live.console.announce_leader', { alias: next.leader.alias_no, amount: money.format(next.leader.amount_minor) }))
  }
  // Opening, final window and BAFO politely; the close, award and cancellation assertively (S3, S9).
  const transition = detectTransition(previous, next)
  if (transition) {
    const text = t(`live.announce.${transition}`)
    if (transition === 'opened' || transition === 'final_window' || transition === 'bafo_started') lifecycleMessage.value = text
    else closedMessage.value = text
  }
})

onBeforeUnmount(() => {
  if (highlightTimer) clearTimeout(highlightTimer)
})

// ---------- Offer feed ----------

const entries = ref<OfferLogEntry[]>([])
const feedLoading = ref(false)
let filling = false
/** A fill asked for while another runs: the lowest `after_seq` still to read. */
let pendingFill: number | null = null
/**
 * The `seq` the feed was loaded after (null until the first load succeeds). An `offer.accepted`
 * beyond `max(base, last held) + 1` means events were missed, even when the feed is still empty:
 * the first offers of a competition arrive right after it opens, while the socket is busiest.
 */
let feedBaseSeq: number | null = null

async function fillFrom(afterSeq: number): Promise<boolean> {
  const id = competition.value?.id
  if (!id) return false
  if (filling) {
    pendingFill = pendingFill === null ? afterSeq : Math.min(pendingFill, afterSeq)
    return false
  }
  filling = true
  let complete = false
  try {
    let cursor = afterSeq
    for (;;) {
      const page = await fetchOfferLog(id, cursor, 200)
      entries.value = mergeOfferEntries(entries.value, page.entries).slice(-OFFER_FEED_SIZE * 5)
      if (!page.has_more || page.last_seq <= cursor) break
      cursor = page.last_seq
    }
    complete = true
  }
  catch {
    // The feed is informative; the ranking and metrics still come from snapshots.
  }
  finally {
    filling = false
  }
  if (pendingFill !== null) {
    const next = pendingFill
    pendingFill = null
    void fillFrom(next)
  }
  return complete
}

async function loadFeed(): Promise<void> {
  if (!competition.value || competition.value.status === 'draft') return
  feedLoading.value = true
  const total = snapshot.value?.metrics.offers_count ?? competition.value.counts.offers
  const from = Math.max(0, total - OFFER_FEED_SIZE)
  if (await fillFrom(from)) feedBaseSeq = from
  feedLoading.value = false
}

onMounted(loadFeed)

ctx.on('offerAccepted', (entry) => {
  const known = entries.value.length > 0 || feedBaseSeq !== null
  const last = Math.max(lastOfferSeq(entries.value), feedBaseSeq ?? 0)
  if (known && offerSeqGap(last, entry)) void fillFrom(last)
  entries.value = mergeOfferEntries(entries.value, [entry]).slice(-OFFER_FEED_SIZE * 5)
})

const metrics = computed(() => {
  const m = snapshot.value?.metrics
  if (!m) return []
  return [
    { key: 'offers', value: m.offers_count },
    { key: 'joined', value: m.participants_joined },
    { key: 'with_offers', value: m.participants_with_offers },
    { key: 'invited', value: m.invitations_count },
  ]
})

const reserveLabel = computed(() => {
  const met = snapshot.value?.reserve_met
  if (met === null || met === undefined) return null
  return met ? td('live.console.reserve.met') : td('live.console.reserve.not_met')
})
</script>

<template>
  <div
    v-if="competition"
    class="flex flex-col gap-6"
  >
    <p
      class="sr-only"
      aria-live="polite"
      aria-atomic="true"
    >
      {{ leaderAnnouncer.message.value }}
    </p>
    <p
      class="sr-only"
      aria-live="polite"
      aria-atomic="true"
    >
      {{ lifecycleMessage }}
    </p>
    <p
      class="sr-only"
      aria-live="assertive"
      aria-atomic="true"
    >
      {{ closedMessage }}
    </p>

    <UiBanner
      v-if="connectionBanner"
      tone="warning"
      role="alert"
    >
      {{ ctx.connection.value === 'polling' ? t('live.console.polling', { seconds: pollSeconds }) : t('common.connection.reconnecting') }}
    </UiBanner>

    <UiAlert
      v-if="extension"
      tone="info"
      dismissible
      :title="t(`live.console.extended.${extension.reason}`)"
      @dismiss="extension = null"
    >
      <span role="status">
        {{ extension.minutes !== null ? t('live.console.extended.body', { minutes: extension.minutes, time: date.formatDeadline(extension.closeAt) }) : t('live.console.extended.body_no_minutes', { time: date.formatDeadline(extension.closeAt) }) }}
      </span>
    </UiAlert>

    <!-- Header strip -->
    <UiCard padding="sm">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex flex-col gap-1">
          <span class="text-sm text-fg-muted">{{ countdownLabel }}</span>
          <UiCountdown
            v-if="countdownTarget"
            :ends-at="countdownTarget"
            :label="countdownLabel"
            :ended-label="status === 'scheduled' ? t('competitions.detail.countdown.opening') : t('competitions.detail.countdown.closing')"
            size="lg"
            announce
          />
          <span
            v-else
            class="text-lg font-bold text-fg"
          >{{ status ? t(`competitions.status.${status === 'live' ? 'live' : status}`) : '—' }}</span>
          <span
            v-if="snapshot?.hard_stop_at && status === 'live'"
            class="text-xs text-fg-muted"
          >
            {{ t('competitions.detail.countdown.latest_close') }}
            <UiDateTime
              :value="snapshot.hard_stop_at"
              format="time"
            />
          </span>
        </div>
        <div class="flex flex-wrap items-center gap-4 text-sm">
          <span
            v-if="(snapshot?.extension_count ?? 0) > 0"
            class="text-fg-muted"
          >{{ t('competitions.detail.countdown.extensions', { count: snapshot?.extension_count ?? 0 }, snapshot?.extension_count ?? 0) }}</span>
          <span
            v-if="snapshot"
            class="inline-flex items-center gap-1.5 text-fg"
          >
            <Radio
              :size="16"
              class="text-brand"
              aria-hidden="true"
            />
            {{ t('live.console.online', { count: snapshot.online_participants_count }, snapshot.online_participants_count) }}
          </span>
        </div>
      </div>
    </UiCard>

    <!-- Not yet open: pre-open panel -->
    <UiCard
      v-if="status === 'scheduled'"
      :title="t('live.console.pre_open.title')"
      :description="t('live.console.pre_open.body')"
    >
      <dl class="grid grid-cols-2 gap-4 sm:grid-cols-3">
        <div>
          <dt class="text-sm text-fg-muted">
            {{ t('competitions.issuer.counts.invitations') }}
          </dt>
          <dd class="text-2xl font-bold text-fg tabular-nums">
            {{ snapshot?.metrics.invitations_count ?? competition.counts.invitations }}
          </dd>
        </div>
        <div>
          <dt class="text-sm text-fg-muted">
            {{ t('competitions.issuer.counts.joined') }}
          </dt>
          <dd class="text-2xl font-bold text-fg tabular-nums">
            {{ snapshot?.metrics.participants_joined ?? competition.counts.joined }}
          </dd>
        </div>
      </dl>
    </UiCard>

    <template v-else-if="snapshot">
      <CompetitionsIssuerBafoRoundCard
        v-if="snapshot.bafo && (status === 'bafo_round' || snapshot.bafo.status === 'running')"
        :cutoff-at="snapshot.bafo.cutoff_at"
        :shortlist-count="snapshot.bafo.shortlist_count"
        :submitted-count="snapshot.bafo.submitted_count"
        :ended="snapshot.bafo.status === 'ended'"
      />

      <div class="grid gap-4 md:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
        <!-- Leading offer -->
        <UiCard padding="sm">
          <div class="flex flex-col gap-3">
            <h2 class="text-sm font-bold text-fg-muted">
              {{ t('glossary.leading_offer') }}
            </h2>
            <UiAlert
              v-if="sealedLocked"
              tone="info"
            >
              {{ t('live.console.sealed_locked') }}
            </UiAlert>
            <div
              v-else-if="snapshot.leader"
              class="flex flex-col gap-1 rounded-md transition-colors duration-300 motion-reduce:transition-none"
              :class="leaderFlash && 'bg-primary-soft'"
            >
              <UiAmount
                :minor="snapshot.leader.amount_minor"
                size="xl"
              />
              <p class="text-sm text-fg">
                {{ t('offers.participant_alias', { number: snapshot.leader.alias_no }) }} · {{ snapshot.leader.organization.name }}
              </p>
              <p class="text-xs text-fg-muted">
                {{ t('live.console.leader_since') }}
                <UiDateTime
                  :value="snapshot.leader.accepted_at"
                  format="time"
                />
              </p>
            </div>
            <p
              v-else
              class="text-sm text-fg-muted"
            >
              {{ t('live.console.no_leader') }}
            </p>
            <UiBadge
              v-if="reserveLabel"
              :tone="snapshot.reserve_met ? 'primary' : 'neutral'"
              :icon="snapshot.reserve_met ? CheckCircle2 : CircleMinus"
              class="self-start"
            >
              {{ reserveLabel }}
            </UiBadge>
          </div>
        </UiCard>

        <!-- Improvement -->
        <UiCard padding="sm">
          <div class="flex flex-col gap-1">
            <h2 class="flex items-center gap-1.5 text-sm font-bold text-fg-muted">
              <TrendingUp
                :size="16"
                aria-hidden="true"
              />
              {{ td('live.metric.improvement') }}
            </h2>
            <bdi
              v-if="snapshot.metrics.improvement_vs_start_bps !== null"
              class="text-2xl font-bold text-fg tabular-nums"
            >{{ formatBpsPercent(snapshot.metrics.improvement_vs_start_bps, { signed: true }) }}</bdi>
            <span
              v-else
              class="text-sm text-fg-muted"
            >{{ t('live.console.improvement_na') }}</span>
          </div>
        </UiCard>
      </div>

      <!-- Metrics -->
      <dl class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div
          v-for="metric in metrics"
          :key="metric.key"
          class="rounded-lg border border-line bg-surface p-4"
        >
          <dt class="text-xs text-fg-muted">
            {{ t(`live.console.metrics.${metric.key}`) }}
          </dt>
          <dd class="text-xl font-bold text-fg tabular-nums">
            {{ metric.value }}
          </dd>
        </div>
      </dl>

      <section class="flex flex-col gap-3">
        <h2 class="flex items-center gap-2 text-base font-bold text-fg">
          <Users
            :size="18"
            aria-hidden="true"
          />
          {{ t('live.console.ranking.title') }}
        </h2>
        <CompetitionsIssuerRankingTable
          :rows="snapshot.ranking"
          :locked="sealedLocked"
          :bafo="Boolean(snapshot.bafo)"
          :changed="changedRows"
        />
      </section>

      <UiCard
        :title="t('live.console.feed.title')"
        padding="sm"
      >
        <CompetitionsIssuerOfferFeed
          :entries="entries"
          :loading="feedLoading"
        />
        <template #footer>
          <UiButton
            :to="`${base}/offers`"
            variant="link"
          >
            {{ t('live.console.feed.all') }}
          </UiButton>
        </template>
      </UiCard>
    </template>

    <div
      v-else-if="status !== 'draft'"
      class="flex flex-col gap-3"
      :aria-label="t('common.loading')"
    >
      <UiSkeleton class="h-28 w-full" />
      <UiSkeleton class="h-64 w-full" />
    </div>
  </div>
</template>
