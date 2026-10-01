import { describe, expect, it } from 'vitest'
import { applySnapshot, finishBuffering, initialLiveState, startBuffering } from '~/utils/live-reducer'

interface Snap { v: number, label: string }
const snap = (v: number, label = `v${v}`): Snap => ({ v, label })

describe('live snapshot reducer (ARCHITECTURE §9.3–§9.4)', () => {
  it('applies only snapshots newer than the last applied v', () => {
    let state = initialLiveState<Snap>()
    let result = applySnapshot(state, snap(5))
    expect(result.applied).toBe(true)
    state = result.state
    result = applySnapshot(state, snap(5, 'duplicate'))
    expect(result.applied).toBe(false)
    result = applySnapshot(state, snap(3, 'late'))
    expect(result.applied).toBe(false)
    expect(result.state.snapshot?.label).toBe('v5')
    result = applySnapshot(state, snap(9))
    expect(result.applied).toBe(true)
    expect(result.state.lastAppliedV).toBe(9)
  })

  it('buffers while resyncing, then applies the REST snapshot and newer buffered ones in v order', () => {
    let state = startBuffering(initialLiveState<Snap>())
    state = applySnapshot(state, snap(12)).state
    state = applySnapshot(state, snap(10)).state
    state = applySnapshot(state, snap(8)).state
    expect(state.snapshot).toBeNull()
    expect(state.buffer).toHaveLength(3)

    state = finishBuffering(state, snap(9, 'rest'))
    expect(state.buffering).toBe(false)
    expect(state.buffer).toEqual([])
    expect(state.lastAppliedV).toBe(12)
    expect(state.snapshot?.label).toBe('v12')
  })

  it('keeps the REST snapshot when every buffered one is older', () => {
    let state = startBuffering(initialLiveState<Snap>())
    state = applySnapshot(state, snap(3)).state
    state = finishBuffering(state, snap(7, 'rest'))
    expect(state.snapshot?.label).toBe('rest')
  })

  it('drains the buffer even when the REST fetch failed', () => {
    let state = startBuffering(applySnapshot(initialLiveState<Snap>(), snap(2)).state)
    state = applySnapshot(state, snap(4)).state
    state = finishBuffering(state, null)
    expect(state.lastAppliedV).toBe(4)
  })

  it('never goes back to an older v after a resync', () => {
    let state = applySnapshot(initialLiveState<Snap>(), snap(20)).state
    state = finishBuffering(startBuffering(state), snap(15, 'stale rest'))
    expect(state.snapshot?.v).toBe(20)
  })

  it('applies the first snapshot of a competition with no activity yet (v = 0)', () => {
    const result = applySnapshot(initialLiveState<Snap>(), snap(0, 'initial'))
    expect(result.applied).toBe(true)
    expect(result.state.snapshot?.label).toBe('initial')
    expect(applySnapshot(result.state, snap(0, 'again')).applied).toBe(false)
    expect(finishBuffering(startBuffering(initialLiveState<Snap>()), snap(0, 'rest')).snapshot?.label).toBe('rest')
  })

  it('rejects non-numeric versions', () => {
    expect(applySnapshot(initialLiveState<Snap>(), snap(Number.NaN)).applied).toBe(false)
  })
})
