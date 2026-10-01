import type { ComputedRef, InjectionKey, MaybeRefOrGetter, Ref } from 'vue'
import { fetchLive } from '~/services/bidding'
import { fetchCompetition } from '~/services/competitions'
import type { LiveSnapshot, OfferLogEntry } from '~/types/api/bidding'
import type { Comment, Competition, CompetitionUpdatedEvent, Invitation, ViewerRole } from '~/types/api/competitions'
import type { LiveConnectionState } from '~/utils/connection-state'

/** Events forwarded to the tabs of an open competition (SCREENS S4 "Other events"). */
export interface CompetitionEvents {
  /** Issuer channel. Consumers append by `seq` and fetch `offers/log?after_seq=` on a gap. */
  offerAccepted: OfferLogEntry
  /** Both channels, projected per audience. */
  commentCreated: Comment
  /** Issuer channel. */
  invitationUpdated: Invitation
  /** Both channels; the competition itself is refetched automatically. */
  competitionUpdated: CompetitionUpdatedEvent
  /** Every snapshot actually applied (realtime, poll, resync or an offer response). */
  liveApplied: LiveSnapshot
}

type Listener<K extends keyof CompetitionEvents> = (payload: CompetitionEvents[K]) => void

export interface CompetitionContext {
  id: ComputedRef<string>
  competition: Ref<Competition | null>
  viewerRole: ComputedRef<ViewerRole | null>
  loading: Ref<boolean>
  error: Ref<ApiError | null>
  /** The latest applied snapshot (issuer or participant, by viewer). */
  live: ComputedRef<LiveSnapshot | null>
  connection: ComputedRef<LiveConnectionState>
  /** Connection gate for the offer composer (combine with `live.accepting_offers`). */
  canSubmit: ComputedRef<boolean>
  refetch: () => Promise<void>
  resync: () => Promise<void>
  /** v-guarded apply (use it for the `live` object of an offer response). */
  applyLive: (snapshot: LiveSnapshot) => boolean
  on: <K extends keyof CompetitionEvents>(event: K, listener: Listener<K>) => () => void
}

const CONTEXT_KEY: InjectionKey<CompetitionContext> = Symbol('bafo.competition')

/** Last-change kinds that change `permissions`, so the competition is refetched (SCREENS S4). */
const REFETCH_KINDS = new Set(['status', 'award', 'bafo'])

/**
 * Loads a competition and owns everything live about it, for the detail parent page (SCREENS §2.1,
 * S4): the projection by `viewer_role`, one realtime subscription (issuer or participant channel),
 * the `v`-ordered snapshot reducer with subscribe-buffer-fetch resync, the connection state machine
 * with the polling fallback, and `refetch()`. Every tab reads it with `useCompetitionContext()`.
 */
export function provideCompetition(id: MaybeRefOrGetter<string>): CompetitionContext {
  const auth = useAuthStore()
  const online = useOnline()
  const { available, state: socketState } = useRealtimeStatus()
  const reducer = useLiveReducer<LiveSnapshot>()

  const competitionId = computed(() => toValue(id))
  const competition = ref<Competition | null>(null) as Ref<Competition | null>
  const loading = ref(false)
  const error = ref<ApiError | null>(null)
  const resynced = ref(false)
  const everConnected = ref(false)
  const downSince = ref<number | null>(Date.now())
  const lastPollOkAt = ref<number | null>(null)
  const now = ref(Date.now())
  const listeners = new Map<keyof CompetitionEvents, Set<(payload: never) => void>>()

  const viewerRole = computed<ViewerRole | null>(() => competition.value?.viewer_role ?? null)

  function emit<K extends keyof CompetitionEvents>(event: K, payload: CompetitionEvents[K]): void {
    for (const listener of listeners.get(event) ?? []) (listener as Listener<K>)(payload)
  }

  function on<K extends keyof CompetitionEvents>(event: K, listener: Listener<K>): () => void {
    const set = listeners.get(event) ?? new Set()
    set.add(listener as (payload: never) => void)
    listeners.set(event, set)
    const off = () => set.delete(listener as (payload: never) => void)
    if (getCurrentScope()) onScopeDispose(off)
    return off
  }

  // ---------- Loading ----------

  let loadSeq = 0
  async function refetch(): Promise<void> {
    const seq = ++loadSeq
    loading.value = competition.value === null
    try {
      const next = await fetchCompetition(competitionId.value)
      if (seq !== loadSeq) return
      competition.value = next
      error.value = null
      if (next.viewer_role !== 'invitee' && next.live) applyLive(next.live)
    }
    catch (cause) {
      if (seq !== loadSeq) return
      error.value = cause instanceof ApiError ? cause : null
    }
    finally {
      if (seq === loadSeq) loading.value = false
    }
  }

  const scheduleRefetch = useDebounceFn(() => refetch(), 300)

  // ---------- Snapshots ----------

  function afterApply(snapshot: LiveSnapshot): void {
    emit('liveApplied', snapshot)
    const current = competition.value
    if (current && (snapshot.status !== current.status || REFETCH_KINDS.has(snapshot.last_change.kind))) {
      void scheduleRefetch()
    }
  }

  function applyLive(snapshot: LiveSnapshot): boolean {
    const applied = reducer.apply(snapshot)
    if (applied) afterApply(snapshot)
    return applied
  }

  const live = computed(() => reducer.snapshot.value)

  async function resync(): Promise<void> {
    if (!channelName.value) return
    if (!reducer.resyncing.value) reducer.beginResync()
    resynced.value = false
    let base: LiveSnapshot | null = null
    try {
      base = await fetchLive(competitionId.value)
      lastPollOkAt.value = Date.now()
    }
    catch (cause) {
      // The viewer's role changed (e.g. no longer a participant): the projection decides again.
      if (cause instanceof ApiError && (cause.code === 'not_a_participant' || cause.isNotFound)) void scheduleRefetch()
    }
    const before = reducer.lastAppliedV.value
    reducer.completeResync(base)
    resynced.value = true
    if (reducer.lastAppliedV.value !== before && reducer.snapshot.value) afterApply(reducer.snapshot.value)
  }

  // ---------- Realtime subscription ----------

  const channelName = computed<string | null>(() => {
    const current = competition.value
    if (!current || current.status === 'draft') return null
    if (current.viewer_role === 'issuer') return `competition.${current.id}`
    if (current.viewer_role === 'participant') {
      const organizationId = auth.organization?.id
      return organizationId ? `competition.${current.id}.participant.${organizationId}` : null
    }
    return null // invitees have no channel: refetch on focus and after join
  })

  const subscription = shallowRef<{ name: string, echo: object } | null>(null)

  function leave(): void {
    if (subscription.value) useEcho()?.leave(subscription.value.name)
    subscription.value = null
  }

  function subscribe(name: string): void {
    const echo = useEcho()
    if (!echo) return
    reducer.beginResync()
    resynced.value = false
    const channel = echo.private(name)
    channel
      .listen('.live.updated', (snapshot: LiveSnapshot) => applyLive(snapshot))
      .listen('.competition.updated', (event: CompetitionUpdatedEvent) => {
        emit('competitionUpdated', event)
        void scheduleRefetch()
      })
      .listen('.comment.created', (comment: Comment) => emit('commentCreated', comment))
    if (competition.value?.viewer_role === 'issuer') {
      channel
        .listen('.offer.accepted', (entry: OfferLogEntry) => emit('offerAccepted', entry))
        .listen('.invitation.updated', (invitation: Invitation) => emit('invitationUpdated', invitation))
    }
    // Fires on the first subscription and after every reconnect: resync each time (ARCHITECTURE §9.4).
    channel.subscribed(() => void resync())
    subscription.value = { name, echo }
  }

  watch([channelName, available, () => auth.token], ([name, isAvailable]) => {
    const echo = name && isAvailable ? useEcho() : null
    const current = subscription.value
    if (current && current.name === name && current.echo === echo) return
    leave()
    if (name && echo) subscribe(name)
  })

  // ---------- Connection state and polling fallback ----------

  watch(socketState, (state, previous) => {
    if (state === 'connected') {
      everConnected.value = true
      downSince.value = null
    }
    else if (previous === 'connected' || downSince.value === null) {
      downSince.value = Date.now()
      resynced.value = false
    }
  }, { immediate: true })

  const connection = computed<LiveConnectionState>(() => liveConnectionState({
    online: online.value,
    socketConnected: socketState.value === 'connected' && subscription.value !== null,
    resynced: resynced.value,
    downSince: downSince.value,
    everConnected: everConnected.value,
    now: now.value,
  }))

  const clock = useServerTime()
  const remainingMs = computed(() => {
    void now.value // re-evaluated every second
    const closeAt = live.value?.effective_close_at
    const at = closeAt ? Date.parse(closeAt) : Number.NaN
    return Number.isNaN(at) ? null : at - clock.now()
  })

  const pollInterval = computed(() => pollIntervalMs(remainingMs.value))

  const canSubmit = computed(() => canSubmitInState({
    state: connection.value,
    hasSnapshot: live.value !== null,
    lastPollOkAt: lastPollOkAt.value,
    pollInterval: pollInterval.value,
    now: now.value,
  }))

  let pollTimer: ReturnType<typeof setTimeout> | null = null

  async function pollOnce(): Promise<void> {
    try {
      const snapshot = await fetchLive(competitionId.value)
      lastPollOkAt.value = Date.now()
      applyLive(snapshot)
    }
    catch {
      // Keep polling; the connection banner explains the delay.
    }
  }

  function stopPolling(): void {
    if (pollTimer) clearTimeout(pollTimer)
    pollTimer = null
  }

  function schedulePoll(): void {
    stopPolling()
    pollTimer = setTimeout(async () => {
      await pollOnce()
      if (connection.value === 'polling' && channelNameOrLiveViewer()) schedulePoll()
    }, pollInterval.value)
  }

  /** Issuers and participants of a published competition have a live snapshot to poll. */
  function channelNameOrLiveViewer(): boolean {
    const current = competition.value
    return Boolean(current && current.status !== 'draft' && current.viewer_role !== 'invitee')
  }

  watch(connection, (state) => {
    if (state === 'polling' && channelNameOrLiveViewer()) {
      // The socket may never have delivered a subscription: stop waiting for it, or every polled
      // snapshot would stay buffered. A later reconnect resyncs again.
      if (reducer.resyncing.value) reducer.completeResync(null)
      void pollOnce()
      schedulePoll()
    }
    else {
      stopPolling()
    }
  })

  // ---------- Lifecycle ----------

  const { pause: stopTicking } = useIntervalFn(() => {
    now.value = Date.now()
  }, 1000)

  function onVisibility(): void {
    if (document.visibilityState !== 'visible') return
    if (viewerRole.value === 'invitee') void refetch()
    else if (subscription.value) void resync()
  }

  function onFocus(): void {
    if (viewerRole.value === 'invitee') void refetch()
  }

  if (import.meta.client) {
    document.addEventListener('visibilitychange', onVisibility)
    window.addEventListener('focus', onFocus)
  }

  watch(competitionId, (next, previous) => {
    if (next === previous) return
    leave()
    reducer.reset()
    competition.value = null
    void refetch()
  })

  onScopeDispose(() => {
    leave()
    stopPolling()
    stopTicking()
    if (import.meta.client) {
      document.removeEventListener('visibilitychange', onVisibility)
      window.removeEventListener('focus', onFocus)
    }
  })

  void refetch()

  const context: CompetitionContext = {
    id: competitionId,
    competition,
    viewerRole,
    loading,
    error,
    live,
    connection,
    canSubmit,
    refetch,
    resync,
    applyLive,
    on,
  }
  provide(CONTEXT_KEY, context)
  return context
}

/** The competition provided by the detail parent page (`provideCompetition`). */
export function useCompetitionContext(): CompetitionContext {
  const context = inject(CONTEXT_KEY, null)
  if (!context) throw new Error('useCompetitionContext() needs provideCompetition() in a parent component')
  return context
}
