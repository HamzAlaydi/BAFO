/**
 * The side-navigation entry to mark as current when the URL alone does not decide it. The shared
 * competition detail `/dashboard/competitions/{id}` (SCREENS CD1) belongs to "My competitions" for the
 * issuer but to "Participating" for participants and invitees: the detail page sets this while it is
 * open. `null` falls back to matching the URL.
 */
export function useNavHighlight() {
  return useState<string | null>('nav:highlight', () => null)
}
