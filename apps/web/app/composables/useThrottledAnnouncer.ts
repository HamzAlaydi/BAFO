/**
 * A polite screen-reader announcement throttled to one per `gapMs` (SCREENS S9: standing and price
 * changes at most once every 10 s). Bind `message` to an `aria-live="polite"` region. Within the gap
 * the latest text wins; the timer is cleared when the component unmounts.
 *
 *   const leader = useThrottledAnnouncer()
 *   leader.announce(() => t('live.leading_amount', { amount }))
 *   <p class="sr-only" aria-live="polite" aria-atomic="true">{{ leader.message.value }}</p>
 */
export function useThrottledAnnouncer(gapMs = 10_000) {
  const message = ref('')
  let lastAt = Number.NEGATIVE_INFINITY
  let pending: ReturnType<typeof setTimeout> | null = null

  function cancel(): void {
    if (pending) clearTimeout(pending)
    pending = null
  }

  /** `text` may be a getter, read when the announcement is actually made (the latest value wins). */
  function announce(text: string | (() => string)): void {
    const read = typeof text === 'function' ? text : () => text
    cancel()
    const flush = () => {
      pending = null
      const value = read()
      if (!value) return
      message.value = value
      lastAt = Date.now()
    }
    const wait = lastAt + gapMs - Date.now()
    if (wait <= 0) flush()
    else pending = setTimeout(flush, wait)
  }

  if (getCurrentInstance()) onBeforeUnmount(cancel)

  return { message, announce, cancel }
}
