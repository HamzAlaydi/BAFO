/**
 * Version-ordered application of live snapshots (ARCHITECTURE §9.3–§9.4, CONVENTIONS §7, SCREENS S4).
 * Pure functions: the composable `useLiveReducer()` wraps them with reactive state.
 *
 * Rules:
 * - a snapshot is applied only if its `v` is greater than the last applied `v`. Versions start at 0
 *   (ARCHITECTURE §5.6 `competition_live_states.version`, default 0), so nothing is applied yet is −1;
 * - while resyncing, incoming snapshots are buffered; the REST snapshot is applied first, then the
 *   buffered ones with a higher `v`, in `v` order.
 */
export interface Versioned {
  v: number
}

export interface LiveState<T extends Versioned> {
  snapshot: T | null
  /** −1 until the first snapshot: a competition with no activity yet is at `v = 0`. */
  lastAppliedV: number
  buffering: boolean
  buffer: T[]
}

export function initialLiveState<T extends Versioned>(): LiveState<T> {
  return { snapshot: null, lastAppliedV: -1, buffering: false, buffer: [] }
}

export interface ApplyResult<T extends Versioned> {
  state: LiveState<T>
  applied: boolean
}

/** Applies (or buffers) one snapshot; drops it when `v ≤ lastAppliedV`. */
export function applySnapshot<T extends Versioned>(state: LiveState<T>, snapshot: T): ApplyResult<T> {
  if (state.buffering) {
    return { state: { ...state, buffer: [...state.buffer, snapshot] }, applied: false }
  }
  if (!Number.isFinite(snapshot.v) || snapshot.v <= state.lastAppliedV) return { state, applied: false }
  return { state: { ...state, snapshot, lastAppliedV: snapshot.v }, applied: true }
}

/** Starts a resync: buffer realtime snapshots until the REST snapshot arrives. */
export function startBuffering<T extends Versioned>(state: LiveState<T>): LiveState<T> {
  return { ...state, buffering: true, buffer: [] }
}

/**
 * Ends a resync with the REST snapshot (`GET …/live`), then drains the buffer in `v` order.
 * `base` may be null when the fetch failed: the buffered snapshots are still applied.
 */
export function finishBuffering<T extends Versioned>(state: LiveState<T>, base: T | null): LiveState<T> {
  let next: LiveState<T> = { ...state, buffering: false, buffer: [] }
  const ordered = [...(base ? [base] : []), ...[...state.buffer].sort((a, b) => a.v - b.v)]
  for (const snapshot of ordered) next = applySnapshot(next, snapshot).state
  return next
}
