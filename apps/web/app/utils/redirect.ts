/**
 * Accepts only same-origin relative paths for post-login redirects ("/ar/dashboard"),
 * never absolute or protocol-relative URLs ("https://evil", "//evil", "/\\evil").
 */
export function safeRedirect(value: unknown, fallback: string): string {
  if (typeof value !== 'string') return fallback
  if (!value.startsWith('/') || value.startsWith('//') || value.startsWith('/\\')) return fallback
  return value
}
