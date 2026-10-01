import type { FragmentToken, PendingTokenKind } from '~/utils/pending-token'

/**
 * Pending e-mail-link tokens (SCREENS CD7): captured from the URL fragment, removed from the address
 * bar with `history.replaceState`, and kept in `sessionStorage` only for the flow
 * (`bafo.pending_invitation`, `bafo.pending_team_invitation`). Storage failures (private windows,
 * blocked site data) degrade to the in-memory value of the current page.
 */
export function usePendingToken(kind: PendingTokenKind) {
  const key = PENDING_TOKEN_KEYS[kind]
  const memory = useState<string | null>(`bafo:pending-token:${kind}`, () => null)

  function read(): string | null {
    if (memory.value) return memory.value
    if (!import.meta.client) return null
    try {
      return window.sessionStorage.getItem(key)
    }
    catch {
      return null
    }
  }

  function store(token: string): void {
    memory.value = token
    if (!import.meta.client) return
    try {
      window.sessionStorage.setItem(key, token)
    }
    catch {
      // Keep the in-memory copy only.
    }
  }

  function clear(): void {
    memory.value = null
    if (!import.meta.client) return
    try {
      window.sessionStorage.removeItem(key)
    }
    catch {
      // Nothing stored.
    }
  }

  /**
   * Reads `#t=…[&action=…]`, stores the token and strips the fragment from the address bar.
   * Returns the fragment values, or null when there was no token in the URL.
   */
  function captureFromLocation(): FragmentToken | null {
    if (!import.meta.client) return null
    const parsed = parseTokenFragment(window.location.hash)
    if (!parsed) return null
    store(parsed.token)
    window.history.replaceState(window.history.state, '', `${window.location.pathname}${window.location.search}`)
    return parsed
  }

  return { read, store, clear, captureFromLocation }
}
