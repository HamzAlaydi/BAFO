import { describe, expect, it } from 'vitest'
import { PENDING_TOKEN_KEYS, parseTokenFragment } from '~/utils/pending-token'

describe('parseTokenFragment (SCREENS CD7)', () => {
  it('reads the token and the optional action from the fragment', () => {
    expect(parseTokenFragment('#t=abc123')).toEqual({ token: 'abc123', action: null })
    expect(parseTokenFragment('#t=abc123&action=decline')).toEqual({ token: 'abc123', action: 'decline' })
    expect(parseTokenFragment('t=xyz')).toEqual({ token: 'xyz', action: null })
  })

  it('rejects missing, blank or oversized tokens', () => {
    expect(parseTokenFragment('')).toBeNull()
    expect(parseTokenFragment(null)).toBeNull()
    expect(parseTokenFragment('#action=decline')).toBeNull()
    expect(parseTokenFragment('#t=')).toBeNull()
    expect(parseTokenFragment(`#t=${'a'.repeat(600)}`)).toBeNull()
  })

  it('uses the storage keys named by the contract', () => {
    expect(PENDING_TOKEN_KEYS.invitation).toBe('bafo.pending_invitation')
    expect(PENDING_TOKEN_KEYS.team_invitation).toBe('bafo.pending_team_invitation')
  })
})
