import { describe, expect, it } from 'vitest'
import { webDeviceName } from '~/utils/device'
import { uuidv4 } from '~/utils/uuid'

describe('webDeviceName (SCREENS W04)', () => {
  it('names the browser and operating system', () => {
    expect(webDeviceName('Mozilla/5.0 (Macintosh; Intel Mac OS X 14_5) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Safari/605.1.15')).toBe('Web · Safari on macOS')
    expect(webDeviceName('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36 Edg/126.0')).toBe('Web · Edge on Windows')
    expect(webDeviceName('Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Mobile Safari/537.36')).toBe('Web · Chrome on Android')
    expect(webDeviceName(null)).toBe('Web · Browser on Unknown OS')
  })

  it('stays within 120 characters', () => {
    expect(webDeviceName('x'.repeat(500)).length).toBeLessThanOrEqual(120)
  })
})

describe('uuidv4 (Idempotency-Key)', () => {
  it('produces distinct RFC 4122 v4 identifiers accepted by the API (8–64 of [A-Za-z0-9_-])', () => {
    const a = uuidv4()
    const b = uuidv4()
    expect(a).toMatch(/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/)
    expect(a).toMatch(/^[A-Za-z0-9_-]{8,64}$/)
    expect(a).not.toBe(b)
  })
})
