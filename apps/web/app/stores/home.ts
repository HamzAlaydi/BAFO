import { fetchHome } from '~/services/competitions'
import type { Home, HomeAlertCode } from '~/types/api/competitions'

const DISMISSED_KEY = 'bafo.dismissed_alerts'

/**
 * `GET /home` (API.md §2.13), shared by the overview page (W10) and the global banners under the
 * top bar (SCREENS §2.3). Alerts are loaded once per session and refreshed with the page; a banner
 * dismissed by the user stays hidden for the browser session (per-viewer convenience).
 */
export const useHomeStore = defineStore('home', () => {
  const auth = useAuthStore()
  const home = ref<Home | null>(null)
  const loading = ref(false)
  const error = ref<ApiError | null>(null)
  const dismissed = ref<HomeAlertCode[]>(readDismissed())
  let inFlight: Promise<void> | null = null

  const alerts = computed(() => (home.value?.alerts ?? []).filter(alert => !dismissed.value.includes(alert.code)))

  function load(): Promise<void> {
    if (inFlight) return inFlight
    loading.value = true
    inFlight = fetchHome()
      .then((next) => {
        home.value = next
        error.value = null
      })
      .catch((cause: unknown) => {
        error.value = cause instanceof ApiError ? cause : new ApiError({ status: null, code: 'network_error', message: '', errors: {} })
      })
      .finally(() => {
        loading.value = false
        inFlight = null
      })
    return inFlight
  }

  async function ensureLoaded(): Promise<void> {
    if (!home.value) await load()
  }

  function dismiss(code: HomeAlertCode): void {
    if (!dismissed.value.includes(code)) dismissed.value = [...dismissed.value, code]
    writeDismissed(dismissed.value)
  }

  // Another user's stats must never show after a sign-in switch.
  watch(() => auth.user?.id ?? null, (id, previous) => {
    if (id !== previous) {
      home.value = null
      error.value = null
    }
  })

  return { home, loading, error, alerts, load, ensureLoaded, dismiss }
})

function readDismissed(): HomeAlertCode[] {
  if (!import.meta.client) return []
  try {
    const parsed: unknown = JSON.parse(window.sessionStorage.getItem(DISMISSED_KEY) ?? '[]')
    return Array.isArray(parsed) ? parsed.filter((code): code is HomeAlertCode => typeof code === 'string') : []
  }
  catch {
    return []
  }
}

function writeDismissed(codes: HomeAlertCode[]): void {
  if (!import.meta.client) return
  try {
    window.sessionStorage.setItem(DISMISSED_KEY, JSON.stringify(codes))
  }
  catch {
    // Storage unavailable: the banner stays dismissed for this page view only.
  }
}
