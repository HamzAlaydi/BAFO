import type { MaybeRefOrGetter } from 'vue'

export interface UseCountdownOptions {
  /** Re-evaluation interval; the value only changes when the displayed second changes (SCREENS S3). */
  tickMs?: number
  /** While mounted: `GET /time` now, every 60 s and on resume (default true). */
  keepSynced?: boolean
  /** Called when 10, 5 or 1 minute(s) remain (crossed while on screen, not on first render). */
  onThreshold?: (minutes: number) => void
  /** Called once when the remaining time reaches zero. Do not treat it as "closed" (S3 step 9). */
  onExpire?: () => void
}

/**
 * Time left until `target` (UTC ISO) on the **server** clock (SCREENS S3 on top of `useServerTime()`).
 * `remainingMs` is null before mount (SSR renders a placeholder) and when there is no target.
 *
 *   const { display, warning, expired } = useCountdown(() => competition.value?.schedule.effective_close_at)
 */
export function useCountdown(target: MaybeRefOrGetter<string | null | undefined>, options: UseCountdownOptions = {}) {
  const clock = useServerTime()
  const remainingMs = ref<number | null>(null)
  let lastSecond: number | null = null
  let lastMs: number | null = null
  let mounted = false

  const targetMs = computed(() => {
    const value = toValue(target)
    const parsed = value ? Date.parse(value) : Number.NaN
    return Number.isNaN(parsed) ? null : parsed
  })

  function update(): void {
    const at = targetMs.value
    if (at === null) {
      remainingMs.value = null
      lastSecond = null
      lastMs = null
      return
    }
    const next = Math.max(0, at - clock.now())
    const second = Math.ceil(next / 1000)
    if (lastMs !== null && options.onThreshold) {
      const minutes = crossedThreshold(lastMs, next)
      if (minutes !== null) options.onThreshold(minutes)
    }
    const expiredNow = next === 0 && (lastMs === null || lastMs > 0)
    lastMs = next
    if (second !== lastSecond) {
      lastSecond = second
      remainingMs.value = next
    }
    if (expiredNow && lastSecond === 0) options.onExpire?.()
  }

  const { pause, resume } = useIntervalFn(update, options.tickMs ?? 250, { immediate: false, immediateCallback: false })
  let stopSync: (() => void) | null = null

  onMounted(() => {
    mounted = true
    update()
    resume()
    if (options.keepSynced !== false) stopSync = clock.keepSynced()
  })

  onBeforeUnmount(() => {
    mounted = false
    pause()
    stopSync?.()
  })

  // A new target (extension, next phase) restarts the thresholds from the new value.
  watch(targetMs, () => {
    lastMs = null
    lastSecond = null
    if (mounted) update()
  })

  const display = computed(() => (remainingMs.value === null ? null : countdownDisplay(remainingMs.value)))
  const expired = computed(() => remainingMs.value === 0)
  const warning = computed(() => remainingMs.value !== null && remainingMs.value > 0 && remainingMs.value <= COUNTDOWN_WARNING_MS)

  return {
    remainingMs: readonly(remainingMs),
    display,
    expired,
    warning,
    update,
  }
}
