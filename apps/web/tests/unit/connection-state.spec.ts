import { describe, expect, it } from 'vitest'
import { canSubmitInState, liveConnectionState, pollIntervalMs, type ConnectionInput } from '~/utils/connection-state'

const NOW = 1_000_000
const base: ConnectionInput = { online: true, socketConnected: false, resynced: false, downSince: NOW, everConnected: false, now: NOW }

describe('liveConnectionState (SCREENS S4)', () => {
  it('connecting while the screen opens, connected once resynced', () => {
    expect(liveConnectionState(base)).toBe('connecting')
    expect(liveConnectionState({ ...base, socketConnected: true, everConnected: true, downSince: null })).toBe('connecting')
    expect(liveConnectionState({ ...base, socketConnected: true, everConnected: true, downSince: null, resynced: true })).toBe('connected')
  })

  it('grace under 3 s after a drop, reconnecting until 10 s, then polling', () => {
    const dropped = { ...base, everConnected: true, downSince: NOW }
    expect(liveConnectionState({ ...dropped, now: NOW + 2999 })).toBe('grace')
    expect(liveConnectionState({ ...dropped, now: NOW + 3000 })).toBe('reconnecting')
    expect(liveConnectionState({ ...dropped, now: NOW + 9999 })).toBe('reconnecting')
    expect(liveConnectionState({ ...dropped, now: NOW + 10_000 })).toBe('polling')
  })

  it('falls back to polling when the socket never connects within 10 s', () => {
    expect(liveConnectionState({ ...base, now: NOW + 9000 })).toBe('connecting')
    expect(liveConnectionState({ ...base, now: NOW + 10_000 })).toBe('polling')
  })

  it('is offline without network, whatever the socket says', () => {
    expect(liveConnectionState({ ...base, online: false, socketConnected: true, resynced: true })).toBe('offline')
  })
})

describe('submit gate', () => {
  const gate = { hasSnapshot: true, lastPollOkAt: null, pollInterval: 3000, now: NOW }

  it('follows the S4 table', () => {
    expect(canSubmitInState({ ...gate, state: 'connected' })).toBe(true)
    expect(canSubmitInState({ ...gate, state: 'grace' })).toBe(true)
    expect(canSubmitInState({ ...gate, state: 'reconnecting' })).toBe(false)
    expect(canSubmitInState({ ...gate, state: 'offline' })).toBe(false)
    expect(canSubmitInState({ ...gate, state: 'connecting' })).toBe(true)
    expect(canSubmitInState({ ...gate, state: 'connecting', hasSnapshot: false })).toBe(false)
  })

  it('while polling, only if the last good poll is younger than two intervals', () => {
    expect(canSubmitInState({ ...gate, state: 'polling', lastPollOkAt: NOW - 5999 })).toBe(true)
    expect(canSubmitInState({ ...gate, state: 'polling', lastPollOkAt: NOW - 6000 })).toBe(false)
    expect(canSubmitInState({ ...gate, state: 'polling', lastPollOkAt: null })).toBe(false)
  })

  it('polls every 3 s in the last 5 minutes, otherwise every 10 s', () => {
    expect(pollIntervalMs(4 * 60_000)).toBe(3000)
    expect(pollIntervalMs(6 * 60_000)).toBe(10_000)
    expect(pollIntervalMs(null)).toBe(10_000)
  })
})
