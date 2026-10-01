import { describe, expect, it } from 'vitest'
import { resolveRealtimeSettings } from '~/utils/realtime'

const fromConfig = { key: 'app-key', host: 'ws.bafo.test', port: 443, scheme: 'https' as const }

describe('resolveRealtimeSettings (ARCHITECTURE §9.1)', () => {
  it('uses AppConfig.realtime', () => {
    expect(resolveRealtimeSettings(fromConfig, { key: '' }, false)).toEqual(fromConfig)
  })

  it('lets a non-empty env key override it in development only', () => {
    const env = { key: 'dev-key', host: 'localhost', port: '8085', scheme: 'http' }
    expect(resolveRealtimeSettings(fromConfig, env, true)).toEqual({ key: 'dev-key', host: 'localhost', port: 8085, scheme: 'http' })
    expect(resolveRealtimeSettings(fromConfig, env, false)).toEqual(fromConfig)
  })

  it('returns null until something usable is known', () => {
    expect(resolveRealtimeSettings(null, { key: '' }, true)).toBeNull()
    expect(resolveRealtimeSettings({ ...fromConfig, key: '' }, null, false)).toBeNull()
  })
})
