/**
 * UUID v4 for `Idempotency-Key` headers (one per user intent, CONVENTIONS §4.2).
 * Uses `crypto.randomUUID()` when available and falls back to `getRandomValues`, which also exists
 * outside secure contexts (the dashboard opened over plain http on a LAN test device).
 */
export function uuidv4(): string {
  if (typeof crypto.randomUUID === 'function') return crypto.randomUUID()
  const bytes = new Uint8Array(16)
  crypto.getRandomValues(bytes)
  bytes[6] = ((bytes[6] ?? 0) & 0x0F) | 0x40
  bytes[8] = ((bytes[8] ?? 0) & 0x3F) | 0x80
  const hex = Array.from(bytes, byte => byte.toString(16).padStart(2, '0')).join('')
  return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`
}
