/**
 * Tokens from e-mail links (SCREENS CD7, ARCHITECTURE D11): read from the URL fragment
 * (`#t=<token>` or `#t=<token>&action=decline`), removed from the address bar, kept in
 * `sessionStorage` for the flow only, and sent in JSON bodies. Never in a query string or log.
 */
export type PendingTokenKind = 'invitation' | 'team_invitation' | 'password_reset'

export const PENDING_TOKEN_KEYS: Record<PendingTokenKind, string> = {
  invitation: 'bafo.pending_invitation',
  team_invitation: 'bafo.pending_team_invitation',
  // CONTRACT-GAP: API.md §1.3 resets with the e-mailed OTP code typed on W08. If the reset mail ever
  // links to `/auth/reset?email=…#t=<code>`, the page takes the code from the fragment this way.
  password_reset: 'bafo.pending_password_reset',
}

export interface FragmentToken {
  token: string
  action: string | null
}

/** Parses `#t=…[&action=…]`; null when there is no usable token. */
export function parseTokenFragment(hash: string | null | undefined): FragmentToken | null {
  if (!hash) return null
  const params = new URLSearchParams(hash.replace(/^#/, ''))
  const token = params.get('t')?.trim()
  if (!token || token.length > 512 || /\s/.test(token)) return null
  const action = params.get('action')
  return { token, action: action ? action.trim() : null }
}
