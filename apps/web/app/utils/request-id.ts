/**
 * A random `X-Request-Id` (32 hex characters; the API accepts 8–64 of `[A-Za-z0-9-]`).
 * Uses `crypto.getRandomValues`, which, unlike `crypto.randomUUID`, also exists outside secure
 * contexts (for example the dashboard opened at http://<LAN IP>:3000 on a test device).
 */
export function createRequestId(): string {
  const bytes = new Uint8Array(16)
  crypto.getRandomValues(bytes)
  return Array.from(bytes, byte => byte.toString(16).padStart(2, '0')).join('')
}
