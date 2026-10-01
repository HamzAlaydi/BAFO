import type { MaybeRefOrGetter } from 'vue'
import { sendHeartbeat } from '~/services/bidding'

const HEARTBEAT_MS = 20_000

/**
 * Participant presence for the issuer's "online now" count (SCREENS S4, ARCHITECTURE §9.5):
 * `POST …/live/heartbeat` on open and every 20 s while the live room is visible; paused while the
 * page is hidden. Failures are ignored (presence is best effort).
 */
export function useLiveHeartbeat(competitionId: MaybeRefOrGetter<string | null | undefined>, enabled: MaybeRefOrGetter<boolean> = true) {
  const visibility = useDocumentVisibility()
  let timer: ReturnType<typeof setInterval> | null = null

  function beat(): void {
    const id = toValue(competitionId)
    if (id) sendHeartbeat(id).catch(() => {})
  }

  function stop(): void {
    if (timer) clearInterval(timer)
    timer = null
  }

  watch([() => toValue(enabled), visibility, () => toValue(competitionId)], ([isEnabled, state, id]) => {
    stop()
    if (!isEnabled || state !== 'visible' || !id) return
    beat()
    timer = setInterval(beat, HEARTBEAT_MS)
  }, { immediate: true })

  onScopeDispose(stop)

  return { stop }
}
