/**
 * The server clock is authoritative for bidding (BRIEF: Time; ARCHITECTURE §9.6; SCREENS S3).
 *
 * Every app v1 response carries `meta.server_time`; `useApi()` records it here with the request's
 * send and receive times. The offset is the **median of the last 3 samples**. Realtime payloads are
 * never samples (their one-way latency is unknown).
 *
 *   const clock = useServerTime()
 *   clock.now()            // best estimate of server time, epoch ms
 *   clock.slow.value       // last round trip > 2 s → show the slow-connection hint
 *   const stop = clock.keepSynced()   // GET /time now, every 60 s and on resume, until stop()
 */
export function useServerTime() {
  const samples = useState<number[]>('bafo:server-time-samples', () => [])
  const roundTripMs = useState<number | null>('bafo:server-time-rtt', () => null)

  const offsetMs = computed(() => median(samples.value) ?? 0)
  const synced = computed(() => samples.value.length > 0)
  const slow = computed(() => (roundTripMs.value ?? 0) > SLOW_ROUND_TRIP_MS)

  function sync(serverTime: string, sentAt?: number, receivedAt: number = Date.now()): void {
    const offset = computeClockOffset(serverTime, receivedAt, sentAt)
    if (offset === null) return
    samples.value = pushSample(samples.value, offset)
    if (sentAt !== undefined && sentAt <= receivedAt) roundTripMs.value = receivedAt - sentAt
  }

  /** Current server time in epoch ms. */
  function now(): number {
    return Date.now() + offsetMs.value
  }

  /** Takes a fresh sample with `GET /time` (the API client records it). Failures keep the last offset. */
  async function refresh(): Promise<void> {
    try {
      await useApi()('/time')
    }
    catch {
      // Offline or failing: countdowns keep running on the last known offset.
    }
  }

  /** Forgets every sample (tests, sign-out). */
  function reset(): void {
    samples.value = []
    roundTripMs.value = null
  }

  return {
    offsetMs,
    synced,
    roundTripMs: readonly(roundTripMs),
    slow,
    sync,
    now,
    refresh,
    reset,
    keepSynced: () => keepServerTimeSynced(refresh),
  }
}

const SYNC_INTERVAL_MS = 60_000
let subscribers = 0
let timer: ReturnType<typeof setInterval> | null = null
let onVisibility: (() => void) | null = null

/**
 * Ref-counted background sync shared by every countdown on screen (SCREENS S3 step 1):
 * `GET /time` at start, every 60 s while at least one subscriber is active, and on resume.
 */
function keepServerTimeSynced(refresh: () => Promise<void>): () => void {
  if (!import.meta.client) return () => {}
  subscribers += 1
  if (subscribers === 1) {
    void refresh()
    timer = setInterval(() => void refresh(), SYNC_INTERVAL_MS)
    onVisibility = () => {
      if (document.visibilityState === 'visible') void refresh()
    }
    document.addEventListener('visibilitychange', onVisibility)
  }
  let stopped = false
  return () => {
    if (stopped) return
    stopped = true
    subscribers -= 1
    if (subscribers === 0) {
      if (timer) clearInterval(timer)
      timer = null
      if (onVisibility) document.removeEventListener('visibilitychange', onVisibility)
      onVisibility = null
    }
  }
}
