import { describe, expect, it } from 'vitest'
import { createRequestId } from '~/utils/request-id'

describe('createRequestId', () => {
  it('matches the X-Request-Id format the API accepts', () => {
    expect(createRequestId()).toMatch(/^[a-f0-9]{32}$/)
  })

  it('differs on every call', () => {
    const ids = new Set(Array.from({ length: 50 }, () => createRequestId()))
    expect(ids.size).toBe(50)
  })
})
