/**
 * Live connection states (SCREENS S4, ARCHITECTURE §9.4) as pure functions.
 *
 * | State          | Enters when                                  | Submit                                     |
 * |----------------|----------------------------------------------|--------------------------------------------|
 * | `connecting`   | screen opens                                 | after the first snapshot                   |
 * | `connected`    | subscribed and resynced                      | enabled                                    |
 * | `grace`        | socket dropped, < 3 s                        | enabled                                    |
 * | `reconnecting` | dropped ≥ 3 s, fallback not yet running      | disabled                                   |
 * | `polling`      | no socket for 10 s                           | while the last good poll < 2 intervals old |
 * | `offline`      | browser offline                              | disabled                                   |
 */
export type LiveConnectionState = 'connecting' | 'connected' | 'grace' | 'reconnecting' | 'polling' | 'offline'

export const GRACE_MS = 3000
export const POLL_AFTER_MS = 10_000
export const POLL_FAST_MS = 3000
export const POLL_SLOW_MS = 10_000
/** Poll fast in the last 5 minutes. */
export const POLL_FAST_WINDOW_MS = 5 * 60_000

export interface ConnectionInput {
  online: boolean
  socketConnected: boolean
  /** The REST snapshot was applied after the latest (re)subscription. */
  resynced: boolean
  /** When the socket was last lost (or the screen opened, if it never connected); null while connected. */
  downSince: number | null
  /** Whether the socket has ever been connected on this screen. */
  everConnected: boolean
  now: number
}

export function liveConnectionState(input: ConnectionInput): LiveConnectionState {
  if (!input.online) return 'offline'
  if (input.socketConnected) return input.resynced ? 'connected' : 'connecting'
  const downFor = input.downSince === null ? 0 : Math.max(0, input.now - input.downSince)
  if (downFor >= POLL_AFTER_MS) return 'polling'
  if (!input.everConnected) return 'connecting'
  return downFor < GRACE_MS ? 'grace' : 'reconnecting'
}

/** Poll every 3 s when fewer than 5 minutes remain, otherwise every 10 s. */
export function pollIntervalMs(remainingMs: number | null): number {
  return remainingMs !== null && remainingMs < POLL_FAST_WINDOW_MS ? POLL_FAST_MS : POLL_SLOW_MS
}

export interface SubmitGateInput {
  state: LiveConnectionState
  hasSnapshot: boolean
  lastPollOkAt: number | null
  pollInterval: number
  now: number
}

/** Whether the offer composer may submit in this connection state (S4 "Submit" column). */
export function canSubmitInState(input: SubmitGateInput): boolean {
  switch (input.state) {
    case 'connected':
    case 'grace':
      return true
    case 'connecting':
      return input.hasSnapshot
    case 'polling':
      return input.lastPollOkAt !== null && input.now - input.lastPollOkAt < 2 * input.pollInterval
    default:
      return false
  }
}
