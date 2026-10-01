import { fetchAppConfig } from '~/services/platform'
import type { AppConfig, LegalDocumentCode } from '~/types/api/platform'

type LoadStatus = 'idle' | 'loading' | 'ready' | 'error'

/**
 * `GET /app-config` (API.md §2.13): realtime settings, legal versions, feature flags, support
 * contacts and maintenance. Loaded at app start (plugin `app-config.client.ts`) and again when the
 * language changes (the maintenance message is localised).
 *
 * Maintenance is entered from the config or from any `503 maintenance` response, and left when a
 * later load says it is over (SCREENS S8: retry every 60 s).
 */
export const useAppConfigStore = defineStore('appConfig', () => {
  const config = ref<AppConfig | null>(null)
  const status = ref<LoadStatus>('idle')
  const maintenanceFromError = ref<string | null>(null)
  let inFlight: Promise<AppConfig | null> | null = null

  const loaded = computed(() => config.value !== null)
  const inMaintenance = computed(() => maintenanceFromError.value !== null || config.value?.maintenance.enabled === true)
  const maintenanceMessage = computed(() => {
    const fromConfig = config.value?.maintenance.enabled ? config.value.maintenance.message : ''
    return fromConfig || maintenanceFromError.value || ''
  })
  const support = computed(() => config.value?.support ?? { email: '', phone: '', whatsapp: '' })
  const sponsorshipEnabled = computed(() => config.value?.features.sponsorship === true)
  const vatRateBp = computed(() => config.value?.vat_rate_bp ?? 1500)

  function legalVersion(code: LegalDocumentCode): string | null {
    return config.value?.legal[code]?.version ?? null
  }

  /** Loads once (concurrent callers share the request); `force` reloads. Never throws. */
  function load(force = false): Promise<AppConfig | null> {
    if (!force && config.value) return Promise.resolve(config.value)
    if (inFlight) return inFlight
    status.value = 'loading'
    inFlight = fetchAppConfig()
      .then((next) => {
        config.value = next
        status.value = 'ready'
        if (!next.maintenance.enabled) maintenanceFromError.value = null
        return next
      })
      .catch(() => {
        status.value = 'error'
        return config.value
      })
      .finally(() => {
        inFlight = null
      })
    return inFlight
  }

  /** A request answered `503 maintenance` (message localised by the server). */
  function enterMaintenance(message: string): void {
    maintenanceFromError.value = message
  }

  /** Retry: reload the config; maintenance ends when the server no longer reports it. */
  async function checkMaintenance(): Promise<boolean> {
    maintenanceFromError.value = null
    // A 503 on this request re-enters maintenance through the API client.
    await load(true)
    return inMaintenance.value
  }

  return {
    config,
    status,
    loaded,
    inMaintenance,
    maintenanceMessage,
    support,
    sponsorshipEnabled,
    vatRateBp,
    legalVersion,
    load,
    enterMaintenance,
    checkMaintenance,
  }
})
