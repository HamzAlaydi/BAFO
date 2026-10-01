import { describe, expect, it } from 'vitest'
import { ApiError, normalizeApiError, parseRetryAfter } from '~/utils/api-error'

const messages = { network: 'offline', unknown: 'unknown' }

describe('normalizeApiError', () => {
  it('keeps the server envelope { message, code, errors }', () => {
    const error = normalizeApiError({
      status: 422,
      data: { message: 'The offer is too high.', code: 'offer_above_ceiling', errors: { amount_minor: ['Too high'] } },
    }, messages)
    expect(error).toBeInstanceOf(ApiError)
    expect(error.toJSON()).toEqual({
      status: 422,
      message: 'The offer is too high.',
      code: 'offer_above_ceiling',
      errors: { amount_minor: ['Too high'] },
      details: {},
    })
    expect(error.fieldError('amount_minor')).toBe('Too high')
    // A 422 business code is not a field-validation failure (CONVENTIONS §8.1).
    expect(error.isValidation).toBe(false)
    expect(normalizeApiError({ status: 422, data: { code: 'validation_failed', errors: {} } }, messages).isValidation).toBe(true)
  })

  it('keeps the documented details of a business error', () => {
    const error = normalizeApiError({
      status: 422,
      data: { message: 'Step not met', code: 'offer_step_not_met', errors: {}, details: { required_amount_minor: 9750000 } },
    }, messages)
    expect(error.details).toEqual({ required_amount_minor: 9750000 })
    expect(normalizeApiError({ status: 409, data: { code: 'conflict', details: 'x' } }, messages).details).toEqual({})
  })

  it('derives a snake_case code from the status when the server sends none', () => {
    const laravelValidation = normalizeApiError({ status: 422, data: { message: 'Invalid', errors: { email: 'Taken' } } }, messages)
    expect(laravelValidation.code).toBe('validation_failed')
    expect(laravelValidation.errors).toEqual({ email: ['Taken'] })

    expect(normalizeApiError({ response: { status: 401 }, data: {} }, messages).code).toBe('unauthenticated')
    expect(normalizeApiError({ statusCode: 503 }, messages).code).toBe('service_unavailable')
    expect(normalizeApiError({ statusCode: 502 }, messages).code).toBe('server_error')
    expect(normalizeApiError({ status: 418 }, messages).code).toBe('http_error')
  })

  it('falls back to the localised unknown message for empty bodies', () => {
    const error = normalizeApiError({ status: 500, data: '<html>' }, messages)
    expect(error.message).toBe('unknown')
    expect(error.errors).toEqual({})
  })

  it('reports requests without a response as network errors', () => {
    const error = normalizeApiError(new TypeError('Failed to fetch'), messages)
    expect(error.code).toBe('network_error')
    expect(error.status).toBeNull()
    expect(error.message).toBe('offline')
  })

  it('recognises aborted requests', () => {
    expect(normalizeApiError({ name: 'AbortError' }, messages).code).toBe('aborted')
  })

  it('returns an existing ApiError unchanged', () => {
    const original = new ApiError({ status: 409, code: 'conflict', message: 'x', errors: {} })
    expect(normalizeApiError(original, messages)).toBe(original)
  })
})

describe('retry hints and details', () => {
  it('reads retry_after_seconds from details first, then the Retry-After header', () => {
    const fromDetails = normalizeApiError({ status: 429, data: { code: 'otp_resend_cooldown', details: { retry_after_seconds: 42.2 } } }, messages)
    expect(fromDetails.retryAfterSeconds).toBe(43)

    const headers = new Headers({ 'Retry-After': '17' })
    const fromHeader = normalizeApiError({ status: 429, response: { status: 429, headers }, data: { code: 'too_many_requests' } }, messages)
    expect(fromHeader.retryAfterSeconds).toBe(17)

    expect(normalizeApiError({ status: 409, data: { code: 'conflict' } }, messages).retryAfterSeconds).toBeNull()
  })

  it('parses HTTP-date Retry-After values', () => {
    const now = Date.parse('2026-10-01T09:00:00Z')
    expect(parseRetryAfter('Thu, 01 Oct 2026 09:00:30 GMT', now)).toBe(30)
    expect(parseRetryAfter('garbage', now)).toBeNull()
    expect(parseRetryAfter(null, now)).toBeNull()
  })

  it('exposes typed details and the retryable classification', () => {
    const error = new ApiError({ status: 422, code: 'offer_step_not_met', message: 'x', errors: {}, details: { required_amount_minor: 9_750_000, opens_at: '2026-10-01T09:00:00.000Z' } })
    expect(error.detailNumber('required_amount_minor')).toBe(9_750_000)
    expect(error.detailNumber('opens_at')).toBeNull()
    expect(error.detailString('opens_at')).toBe('2026-10-01T09:00:00.000Z')
    expect(error.isRetryable).toBe(false)
    expect(new ApiError({ status: null, code: 'network_error', message: '', errors: {} }).isRetryable).toBe(true)
    expect(new ApiError({ status: 502, code: 'server_error', message: '', errors: {} }).isRetryable).toBe(true)
    expect(new ApiError({ status: 503, code: 'maintenance', message: '', errors: {} }).isRetryable).toBe(false)
  })

  it('uses a decoded body (blob downloads) over the raw data', () => {
    const error = normalizeApiError({ status: 409, data: new Uint8Array([1]) }, messages, { code: 'invoice_pdf_not_ready', message: 'Not ready' })
    expect(error.code).toBe('invoice_pdf_not_ready')
    expect(error.message).toBe('Not ready')
  })
})
