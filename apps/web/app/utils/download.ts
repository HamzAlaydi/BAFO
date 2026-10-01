/**
 * Private downloads (API.md §0.7): fetched as a blob with the bearer header, then saved.
 * The bearer token must never go to another origin, so only API-origin URLs are accepted.
 */

/**
 * Resolves a download path against the API:
 * - `/api/app/v1/files/…` (a `File.download_path`) → API origin + path;
 * - `/billing/invoices/…/pdf` (relative to the API base) → API base + path;
 * - an absolute URL → itself, only when it is on the API origin.
 * Returns null for anything else.
 */
export function resolveApiUrl(path: string, apiBase: string): string | null {
  let base: URL
  try {
    base = new URL(apiBase)
  }
  catch {
    return null
  }
  if (/^https?:\/\//i.test(path)) {
    try {
      const url = new URL(path)
      return url.origin === base.origin ? url.toString() : null
    }
    catch {
      return null
    }
  }
  if (path.startsWith('//')) return null
  if (path.startsWith('/api/')) return `${base.origin}${path}`
  if (path.startsWith('/')) return `${base.toString().replace(/\/$/, '')}${path}`
  return null
}

/** The file name from `Content-Disposition` (`filename*=UTF-8''…` preferred), or null. */
export function filenameFromDisposition(header: string | null | undefined): string | null {
  if (!header) return null
  const extended = /filename\*\s*=\s*(?:UTF-8|utf-8)''([^;]+)/.exec(header)
  if (extended?.[1]) {
    try {
      return sanitizeFilename(decodeURIComponent(extended[1].trim().replace(/^"|"$/g, '')))
    }
    catch {
      // fall through to the plain parameter
    }
  }
  const plain = /filename\s*=\s*("([^"]*)"|[^;]+)/.exec(header)
  const value = plain?.[2] ?? plain?.[1]
  return value ? sanitizeFilename(value.trim().replace(/^"|"$/g, '')) : null
}

/** Strips path separators and control characters from a server-supplied file name. */
export function sanitizeFilename(name: string): string {
  // eslint-disable-next-line no-control-regex
  const cleaned = name.replace(/[\u0000-\u001F\u007F/\\]/g, '_').trim()
  return cleaned === '' || cleaned === '.' || cleaned === '..' ? 'download' : cleaned.slice(0, 200)
}
