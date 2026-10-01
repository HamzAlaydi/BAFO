import type { LiveState, Versioned } from '~/utils/live-reducer'

/**
 * Reactive wrapper around the pure `v`-ordered reducer (`utils/live-reducer.ts`, SCREENS §2.1).
 * The competition context owns one; the offer response's `live` object goes through `apply()` too.
 *
 *   const live = useLiveReducer<ParticipantLiveSnapshot>()
 *   live.beginResync()                 // subscribed: buffer realtime snapshots
 *   live.completeResync(await fetchLive(id))
 *   channel.listen('.live.updated', live.apply)
 */
export function useLiveReducer<T extends Versioned>() {
  const state = shallowRef<LiveState<T>>(initialLiveState<T>())

  const snapshot = computed(() => state.value.snapshot)
  const lastAppliedV = computed(() => state.value.lastAppliedV)
  const resyncing = computed(() => state.value.buffering)

  /** Applies a snapshot if its `v` is newer (buffers during a resync). Returns whether it was applied. */
  function apply(next: T): boolean {
    const result = applySnapshot(state.value, next)
    if (result.state !== state.value) state.value = result.state
    return result.applied
  }

  function beginResync(): void {
    state.value = startBuffering(state.value)
  }

  function completeResync(base: T | null): void {
    state.value = finishBuffering(state.value, base)
  }

  function reset(): void {
    state.value = initialLiveState<T>()
  }

  return { snapshot, lastAppliedV, resyncing, apply, beginResync, completeResync, reset }
}
