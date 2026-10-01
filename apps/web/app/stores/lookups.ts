import { fetchLookups } from '~/services/catalog'
import type { CloseReasonKind, Lookups } from '~/types/api/catalog'
import type { AppLocale } from '~/types/api/common'

interface CachedLookups {
  lookups: Lookups
  etag: string | null
}

const EMPTY: Lookups = { regions: [], categories: [], close_reasons: [], presets: [] }

/**
 * Regions, categories, close reasons and presets (`GET /lookups`, API.md §1.2). Names are localised,
 * so the cache is kept per locale and revalidated with `If-None-Match` (304 keeps the cached copy).
 * Loaded on first use (`ensureLoaded()`); once used, a language switch refetches automatically.
 */
export const useLookupsStore = defineStore('lookups', () => {
  const locale = useAppLocale()
  const cache = ref<Partial<Record<AppLocale, CachedLookups>>>({})
  const loading = ref(false)
  const error = ref<ApiError | null>(null)
  const used = ref(false)
  const inFlight = new Map<AppLocale, Promise<void>>()

  const current = computed<Lookups>(() => cache.value[locale.value]?.lookups ?? EMPTY)
  const loaded = computed(() => cache.value[locale.value] !== undefined)

  const regions = computed(() => current.value.regions)
  const categories = computed(() => current.value.categories)
  const closeReasons = computed(() => current.value.close_reasons)
  const presets = computed(() => current.value.presets)

  function closeReasonsOf(kind: CloseReasonKind) {
    return closeReasons.value.filter(reason => reason.kind === kind)
  }

  function regionById(id: string | null | undefined) {
    return id ? regions.value.find(region => region.id === id) ?? null : null
  }

  function categoryById(id: string | null | undefined) {
    return id ? categories.value.find(category => category.id === id) ?? null : null
  }

  /** Fetches (or revalidates) the lookups of the current locale. Throws `ApiError` on failure. */
  function load(): Promise<void> {
    const target = locale.value
    const pending = inFlight.get(target)
    if (pending) return pending
    loading.value = true
    error.value = null
    const request = fetchLookups(cache.value[target]?.etag)
      .then((result) => {
        if (result.status === 'fresh') {
          cache.value = { ...cache.value, [target]: { lookups: result.lookups, etag: result.etag } }
        }
      })
      .catch((cause: unknown) => {
        error.value = cause instanceof ApiError ? cause : null
        throw cause
      })
      .finally(() => {
        inFlight.delete(target)
        loading.value = inFlight.size > 0
      })
    inFlight.set(target, request)
    return request
  }

  /** Loads the current locale's lookups unless they are cached. */
  async function ensureLoaded(): Promise<void> {
    used.value = true
    if (!loaded.value) await load()
  }

  // Server-localised names change with the language (SCREENS S1): revalidate once they are in use.
  watch(locale, () => {
    if (used.value) load().catch(() => {})
  })

  return {
    loading,
    error,
    loaded,
    regions,
    categories,
    closeReasons,
    presets,
    closeReasonsOf,
    regionById,
    categoryById,
    load,
    ensureLoaded,
  }
})
