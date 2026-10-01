import { describe, expect, it } from 'vitest'
import { ApiError } from '~/utils/api-error'
import { bindFieldErrors, errorMessage } from '~/utils/error-message'

const messages: Record<string, string> = {
  'errors.unknown': 'unknown',
  'errors.network': 'network',
  'errors.invalid_credentials': 'bad credentials (client copy)',
}
const t = (key: string) => messages[key] ?? key
const te = (key: string) => key in messages

describe('errorMessage (CONVENTIONS §4.1)', () => {
  it('prefers errors.<code> when the key exists', () => {
    const error = new ApiError({ status: 401, code: 'invalid_credentials', message: 'server copy', errors: {} })
    expect(errorMessage(error, t, te)).toBe('bad credentials (client copy)')
  })

  it('falls back to the localised server message', () => {
    const error = new ApiError({ status: 409, code: 'brand_new_code', message: 'server copy', errors: {} })
    expect(errorMessage(error, t, te)).toBe('server copy')
  })

  it('maps network failures and unknown values', () => {
    expect(errorMessage(new ApiError({ status: null, code: 'network_error', message: '', errors: {} }), t, te)).toBe('network')
    expect(errorMessage(new Error('boom'), t, te)).toBe('unknown')
    expect(errorMessage(new ApiError({ status: 500, code: 'no_key', message: '', errors: {} }), t, te)).toBe('unknown')
  })
})

describe('bindFieldErrors (SCREENS S7)', () => {
  const error = new ApiError({
    status: 422,
    code: 'validation_failed',
    message: 'invalid',
    errors: {
      'organization.cr_number': ['CR taken'],
      'invitations.2.email': ['Bad e-mail', 'second message'],
      'unknown.path': ['Somewhere else'],
    },
  })

  it('binds known paths and collects the rest for a form-level alert', () => {
    const bound = bindFieldErrors(error, ['organization.cr_number', 'invitations.2.email'])
    expect(bound.fields).toEqual({ 'organization.cr_number': 'CR taken', 'invitations.2.email': 'Bad e-mail' })
    expect(bound.unmatched).toEqual(['Somewhere else'])
  })

  it('supports renamed paths', () => {
    const bound = bindFieldErrors(error, ['cr'], { 'organization.cr_number': 'cr' })
    expect(bound.fields).toEqual({ cr: 'CR taken' })
    expect(bound.unmatched).toHaveLength(2)
  })
})
