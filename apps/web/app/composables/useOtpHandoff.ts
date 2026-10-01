import type { OtpPurpose } from '~/types/api/identity'

interface OtpHandoff {
  email: string
  expiresAt: string | null
}

/**
 * Carries an OTP's expiry from the page that triggered it (register, sign-in with an unverified
 * e-mail, forgot password) to the OTP page, in memory only. The e-mail itself travels in the
 * `?email=` query (CONVENTIONS §4.3); the code never leaves the OTP input.
 */
export function useOtpHandoff(purpose: OtpPurpose) {
  const state = useState<OtpHandoff | null>(`bafo:otp-handoff:${purpose}`, () => null)

  function set(email: string, expiresAt: string | null): void {
    state.value = { email: email.trim().toLowerCase(), expiresAt }
  }

  /** The expiry for this e-mail, if the previous page left one. */
  function expiresAtFor(email: string): string | null {
    return state.value && state.value.email === email.trim().toLowerCase() ? state.value.expiresAt : null
  }

  return { set, expiresAtFor }
}
