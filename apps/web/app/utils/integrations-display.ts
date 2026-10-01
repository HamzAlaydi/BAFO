/**
 * Integrations display helpers (SCREENS W25, W36–W42): scope groups and defaults, key states,
 * public-API snippets with placeholders only, and the per-session export list.
 */
import type { ApiKey, ApiScope, ExportJob, ImportRowCode, Vendor, WebhookDelivery } from '../types/api/integrations'
import type { Tone } from '../types/ui'

// ---------- Scopes (ARCHITECTURE §14.4) ----------

/** UI defaults when creating a client: every read scope plus the four everyday write scopes. */
export const DEFAULT_API_SCOPES: readonly ApiScope[] = [
  'organization:read',
  'lookups:read',
  'vendors:read',
  'vendors:write',
  'competitions:read',
  'competitions:write',
  'invitations:read',
  'invitations:write',
  'offers:read',
  'awards:read',
  'awards:sync',
]

/** Scopes grouped by resource, in catalogue order, for the checklist. */
export const API_SCOPE_GROUPS: ReadonlyArray<{ key: string, scopes: readonly ApiScope[] }> = [
  { key: 'organization', scopes: ['organization:read', 'lookups:read'] },
  { key: 'vendors', scopes: ['vendors:read', 'vendors:write'] },
  { key: 'competitions', scopes: ['competitions:read', 'competitions:write', 'competitions:publish', 'competitions:manage'] },
  { key: 'invitations', scopes: ['invitations:read', 'invitations:write'] },
  { key: 'results', scopes: ['offers:read', 'awards:read', 'awards:sync'] },
  { key: 'webhooks', scopes: ['webhooks:manage'] },
]

/** i18n segment of a scope: `competitions:read` → `competitions_read`. */
export function scopeKey(scope: string): string {
  return scope.replace(/[^a-z0-9]+/g, '_')
}

// ---------- Keys and deliveries ----------

export type ApiKeyState = 'active' | 'expired' | 'revoked'

/** Display state of an API key at `nowMs` (the server clock). The server still decides on use. */
export function apiKeyState(key: Pick<ApiKey, 'revoked_at' | 'expires_at'>, nowMs: number): ApiKeyState {
  if (key.revoked_at) return 'revoked'
  if (key.expires_at && Date.parse(key.expires_at) <= nowMs) return 'expired'
  return 'active'
}

export const API_KEY_STATE_TONES: Record<ApiKeyState, Tone> = { active: 'primary', expired: 'neutral', revoked: 'neutral' }

export const DELIVERY_STATUS_TONES: Record<WebhookDelivery['status'], Tone> = { pending: 'info', succeeded: 'primary', failed: 'warning' }

export const JOB_STATUS_TONES: Record<string, Tone> = { queued: 'neutral', processing: 'info', completed: 'primary', failed: 'warning' }

export const IMPORT_ROW_CODES: readonly ImportRowCode[] = ['required', 'invalid_format', 'unknown_region', 'unknown_category', 'duplicate_in_file']

export function isKnownImportRowCode(code: string): code is ImportRowCode {
  return (IMPORT_ROW_CODES as readonly string[]).includes(code)
}

// ---------- Public API snippets (API.md §3.1, §4.3), placeholders only ----------

/** The API origin from the app API base (`http://localhost:8000/api/app/v1` → `http://localhost:8000`). */
export function apiOriginFrom(apiBase: string): string {
  try {
    return new URL(apiBase).origin
  }
  catch {
    return ''
  }
}

export function publicApiBase(origin: string): string {
  return `${origin}/api/public/v1`
}

export function tokenRequestSnippet(origin: string): string {
  return [
    `curl -X POST ${publicApiBase(origin)}/oauth/token \\`,
    '  -u "<CLIENT_ID>:<CLIENT_SECRET>" \\',
    '  -d "grant_type=client_credentials" \\',
    '  -d "scope=competitions:read vendors:write"',
  ].join('\n')
}

export function apiKeyRequestSnippet(origin: string): string {
  return [
    `curl ${publicApiBase(origin)}/ping \\`,
    '  -H "Authorization: Bearer <API_KEY>" \\',
    '  -H "Accept-Language: ar"',
  ].join('\n')
}

/** Standard Webhooks verification (API.md §4.3), Node.js. */
export const WEBHOOK_VERIFY_NODE = [
  `const crypto = require('node:crypto')`,
  '',
  '// secret = "whsec_…", rawBody = the exact request body bytes as a string',
  'function verify(secret, headers, rawBody) {',
  `  const id = headers['webhook-id']`,
  `  const ts = headers['webhook-timestamp']`,
  `  const key = Buffer.from(secret.slice(6), 'base64')`,
  '  const expected = crypto.createHmac(\'sha256\', key).update(`${id}.${ts}.${rawBody}`).digest(\'base64\')',
  `  const matches = headers['webhook-signature'].split(' ').some(s => s.startsWith('v1,')`,
  '    && s.length - 3 === expected.length',
  '    && crypto.timingSafeEqual(Buffer.from(s.slice(3)), Buffer.from(expected)))',
  '  return matches && Math.abs(Date.now() / 1000 - Number(ts)) <= 300',
  '}',
].join('\n')

/** Standard Webhooks verification (API.md §4.3), PHP. */
export const WEBHOOK_VERIFY_PHP = [
  `[$id, $ts, $sigHeader] = [$h['webhook-id'], $h['webhook-timestamp'], $h['webhook-signature']];`,
  '$key = base64_decode(substr($secret, 6));',
  `$expected = base64_encode(hash_hmac('sha256', "$id.$ts.$rawBody", $key, true));`,
  `$ok = collect(explode(' ', $sigHeader))`,
  `    ->contains(fn ($s) => str_starts_with($s, 'v1,') && hash_equals($expected, substr($s, 3)))`,
  '    && abs(time() - (int) $ts) <= 300;',
].join('\n')

// ---------- Field errors ----------

/**
 * Maps indexed error paths of array fields to the field itself (`scopes.2` → `scopes`,
 * `event_types.0` → `event_types`) for `useErrorMessage().bind`.
 */
export function arrayAliases(error: unknown, ...fields: string[]): Record<string, string> {
  const errors = (error as { errors?: Record<string, unknown> } | null)?.errors
  if (!errors || typeof errors !== 'object') return {}
  const aliases: Record<string, string> = {}
  for (const path of Object.keys(errors)) {
    const field = fields.find(name => path.startsWith(`${name}.`))
    if (field) aliases[path] = field
  }
  return aliases
}

// ---------- Webhook URL (feedback only; the server's SSRF guard decides) ----------

/** An absolute http(s) URL without credentials. `https` and public targets are checked by the server. */
export function isWebhookUrlShape(value: string): boolean {
  try {
    const url = new URL(value.trim())
    return (url.protocol === 'https:' || url.protocol === 'http:') && !url.username && !url.password && url.hostname !== ''
  }
  catch {
    return false
  }
}

// ---------- Exports started in this browser session (SCREENS §6 G2) ----------

/** An export job plus what the list shows about its target. No secret is stored. */
export interface SessionExport {
  job: ExportJob
  /** The competition title for results and offer log exports. */
  label: string | null
}

const SESSION_EXPORTS_KEY = 'bafo.export_jobs'
const SESSION_EXPORTS_MAX = 20

export function readSessionExports(): SessionExport[] {
  try {
    const raw = globalThis.sessionStorage?.getItem(SESSION_EXPORTS_KEY)
    const parsed: unknown = raw ? JSON.parse(raw) : []
    if (!Array.isArray(parsed)) return []
    return parsed.filter((entry): entry is SessionExport =>
      Boolean(entry) && typeof entry === 'object' && typeof (entry as SessionExport).job?.id === 'string')
  }
  catch {
    return []
  }
}

export function writeSessionExports(entries: readonly SessionExport[]): void {
  try {
    globalThis.sessionStorage?.setItem(SESSION_EXPORTS_KEY, JSON.stringify(entries.slice(0, SESSION_EXPORTS_MAX)))
  }
  catch {
    // Storage unavailable: the list lives only while the page is open.
  }
}

/** Adds or replaces a job (newest first). */
export function upsertSessionExport(entries: readonly SessionExport[], next: SessionExport): SessionExport[] {
  const rest = entries.filter(entry => entry.job.id !== next.job.id)
  const existing = entries.find(entry => entry.job.id === next.job.id)
  return existing
    ? entries.map(entry => (entry.job.id === next.job.id ? { ...next, label: next.label ?? entry.label } : entry))
    : [next, ...rest].slice(0, SESSION_EXPORTS_MAX)
}

// ---------- Vendors ----------

/** The vendor name for the UI language: the English name in English when there is one. */
export function vendorDisplayName(vendor: Pick<Vendor, 'name' | 'name_en'>, locale: string): string {
  return locale === 'en' && vendor.name_en ? vendor.name_en : vendor.name
}

export const VENDOR_STATUS_TONES: Record<Vendor['status'], Tone> = { active: 'primary', blocked: 'warning', archived: 'neutral' }

/** External system slug (ARCHITECTURE §5.4, API.md §3.3): `sap_s4` or `custom:oracle_ebs`. */
export function isExternalSystemSlug(value: string): boolean {
  return /^[a-z0-9_]+(:[a-z0-9_]+)?$/.test(value)
}

/** One editable external reference row in the vendor form (API.md §2.12 `ExternalRef`). */
export interface ExternalRefDraft {
  key: number
  system: string
  type: string
  id: string
  number: string
  url: string
}

/** Drafts from a vendor's refs, and back to the API shape (empty optional parts become null). */
export function externalRefDrafts(refs: ReadonlyArray<{ system: string, type: string, id: string, number: string | null, url: string | null }>): ExternalRefDraft[] {
  return refs.map((ref, index) => ({ key: index + 1, system: ref.system, type: ref.type, id: ref.id, number: ref.number ?? '', url: ref.url ?? '' }))
}

export function externalRefsPayload(drafts: readonly ExternalRefDraft[]): Array<{ system: string, type: string, id: string, number: string | null, url: string | null }> {
  return drafts.map(draft => ({
    system: draft.system.trim(),
    type: draft.type.trim(),
    id: draft.id.trim(),
    number: draft.number.trim() || null,
    url: draft.url.trim() || null,
  }))
}
