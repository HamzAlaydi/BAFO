import type { RealtimeConfig } from '../types/api/platform'

export interface RealtimeSettings {
  key: string
  host: string
  port: number
  scheme: 'http' | 'https'
}

interface EnvOverride {
  key?: string
  host?: string
  port?: number | string
  scheme?: string
}

/**
 * Where Echo gets its Reverb settings (ARCHITECTURE §9.1, SCREENS §2.1): `AppConfig.realtime` from
 * `GET /app-config`. A non-empty `NUXT_PUBLIC_REVERB_KEY` overrides it **in development only**
 * (for example to point a local build at another Reverb). Returns null when neither is usable.
 */
export function resolveRealtimeSettings(
  fromAppConfig: RealtimeConfig | null | undefined,
  envOverride: EnvOverride | null | undefined,
  isDev: boolean,
): RealtimeSettings | null {
  if (isDev && envOverride?.key) {
    const port = Number(envOverride.port)
    return {
      key: envOverride.key,
      host: envOverride.host || 'localhost',
      port: Number.isFinite(port) && port > 0 ? port : 8085,
      scheme: envOverride.scheme === 'https' ? 'https' : 'http',
    }
  }
  if (fromAppConfig?.key && fromAppConfig.host) {
    return {
      key: fromAppConfig.key,
      host: fromAppConfig.host,
      port: Number(fromAppConfig.port) || (fromAppConfig.scheme === 'https' ? 443 : 80),
      scheme: fromAppConfig.scheme === 'https' ? 'https' : 'http',
    }
  }
  return null
}
