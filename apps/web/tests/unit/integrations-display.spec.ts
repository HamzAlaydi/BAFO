import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import { API_SCOPES, OPT_IN_API_SCOPES } from '~/types/api/integrations'
import {
  API_SCOPE_GROUPS,
  DEFAULT_API_SCOPES,
  WEBHOOK_VERIFY_NODE,
  WEBHOOK_VERIFY_PHP,
  apiKeyRequestSnippet,
  apiKeyState,
  apiOriginFrom,
  arrayAliases,
  externalRefDrafts,
  externalRefsPayload,
  isExternalSystemSlug,
  isKnownImportRowCode,
  isWebhookUrlShape,
  readSessionExports,
  scopeKey,
  tokenRequestSnippet,
  upsertSessionExport,
  vendorDisplayName,
  writeSessionExports,
} from '~/utils/integrations-display'
import { makeExportJob } from '../fixtures/integrations'

describe('API scopes (ARCHITECTURE §14.4)', () => {
  it('defaults to every read scope plus the everyday write scopes, never the opt-in ones', () => {
    const reads = API_SCOPES.filter(scope => scope.endsWith(':read'))
    for (const scope of reads) expect(DEFAULT_API_SCOPES).toContain(scope)
    for (const scope of ['vendors:write', 'competitions:write', 'invitations:write', 'awards:sync']) expect(DEFAULT_API_SCOPES).toContain(scope)
    for (const scope of OPT_IN_API_SCOPES) expect(DEFAULT_API_SCOPES).not.toContain(scope)
  })

  it('groups every scope exactly once', () => {
    const grouped = API_SCOPE_GROUPS.flatMap(group => group.scopes)
    expect([...grouped].sort()).toEqual([...API_SCOPES].sort())
  })

  it('maps scopes to i18n segments', () => {
    expect(scopeKey('competitions:read')).toBe('competitions_read')
    expect(scopeKey('awards:sync')).toBe('awards_sync')
  })
})

describe('apiKeyState', () => {
  const now = Date.parse('2026-10-01T09:00:00.000Z')
  it('is revoked, expired or active on the given clock', () => {
    expect(apiKeyState({ revoked_at: '2026-09-01T00:00:00.000Z', expires_at: null }, now)).toBe('revoked')
    expect(apiKeyState({ revoked_at: null, expires_at: '2026-09-30T00:00:00.000Z' }, now)).toBe('expired')
    expect(apiKeyState({ revoked_at: null, expires_at: '2026-10-02T00:00:00.000Z' }, now)).toBe('active')
    expect(apiKeyState({ revoked_at: null, expires_at: null }, now)).toBe('active')
  })
})

describe('field error aliases', () => {
  it('maps indexed array paths to their field', () => {
    const error = { errors: { 'scopes.2': ['bad'], 'name': ['required'], 'event_types.0': ['bad'] } }
    expect(arrayAliases(error, 'scopes', 'event_types')).toEqual({ 'scopes.2': 'scopes', 'event_types.0': 'event_types' })
    expect(arrayAliases(null, 'scopes')).toEqual({})
  })
})

describe('snippets (placeholders only)', () => {
  it('builds the public API URLs from the app API base', () => {
    const origin = apiOriginFrom('http://localhost:8000/api/app/v1')
    expect(origin).toBe('http://localhost:8000')
    expect(tokenRequestSnippet(origin)).toContain('http://localhost:8000/api/public/v1/oauth/token')
    expect(tokenRequestSnippet(origin)).toContain('<CLIENT_ID>:<CLIENT_SECRET>')
    expect(apiKeyRequestSnippet(origin)).toContain('Bearer <API_KEY>')
    expect(apiOriginFrom('not a url')).toBe('')
  })

  it('verifies Standard Webhooks signatures in both samples (API.md §4.3)', () => {
    for (const snippet of [WEBHOOK_VERIFY_NODE, WEBHOOK_VERIFY_PHP]) {
      expect(snippet).toContain('webhook-id')
      expect(snippet).toContain('300')
      expect(snippet).not.toMatch(/whsec_[A-Za-z0-9+/]{8,}/)
    }
    expect(WEBHOOK_VERIFY_NODE).toContain('timingSafeEqual')
    expect(WEBHOOK_VERIFY_PHP).toContain('hash_equals')
  })
})

describe('webhook URL shape (feedback only)', () => {
  it('accepts absolute http(s) URLs without credentials', () => {
    expect(isWebhookUrlShape('https://erp.example.sa/bafo/webhooks')).toBe(true)
    expect(isWebhookUrlShape('http://localhost:9000/hook')).toBe(true)
    expect(isWebhookUrlShape('https://user:pass@erp.example.sa')).toBe(false)
    expect(isWebhookUrlShape('ftp://erp.example.sa')).toBe(false)
    expect(isWebhookUrlShape('erp.example.sa')).toBe(false)
  })
})

describe('session exports (SCREENS §6 G2)', () => {
  const store = new Map<string, string>()
  beforeEach(() => {
    store.clear()
    Object.defineProperty(globalThis, 'sessionStorage', {
      value: { getItem: (key: string) => store.get(key) ?? null, setItem: (key: string, value: string) => void store.set(key, value) },
      configurable: true,
    })
  })
  afterEach(() => {
    Reflect.deleteProperty(globalThis, 'sessionStorage')
  })

  it('adds new jobs first and updates existing ones without losing the label', () => {
    const first = makeExportJob({ id: 'a' })
    const second = makeExportJob({ id: 'b' })
    let list = upsertSessionExport([], { job: first, label: 'Tender A' })
    list = upsertSessionExport(list, { job: second, label: null })
    expect(list.map(entry => entry.job.id)).toEqual(['b', 'a'])
    list = upsertSessionExport(list, { job: { ...first, status: 'completed' }, label: null })
    expect(list.map(entry => entry.job.id)).toEqual(['b', 'a'])
    expect(list[1]).toMatchObject({ label: 'Tender A', job: { status: 'completed' } })
  })

  it('round-trips through sessionStorage and ignores junk', () => {
    writeSessionExports([{ job: makeExportJob({ id: 'x' }), label: null }])
    expect(readSessionExports().map(entry => entry.job.id)).toEqual(['x'])
    store.set('bafo.export_jobs', JSON.stringify([{ nope: true }, 3]))
    expect(readSessionExports()).toEqual([])
    store.set('bafo.export_jobs', 'broken')
    expect(readSessionExports()).toEqual([])
  })
})

describe('vendors', () => {
  it('shows the English name in English when there is one', () => {
    expect(vendorDisplayName({ name: 'شركة الريادة', name_en: 'Al Riyada' }, 'en')).toBe('Al Riyada')
    expect(vendorDisplayName({ name: 'شركة الريادة', name_en: 'Al Riyada' }, 'ar')).toBe('شركة الريادة')
    expect(vendorDisplayName({ name: 'شركة الريادة', name_en: null }, 'en')).toBe('شركة الريادة')
  })

  it('validates external system slugs (API.md §3.3)', () => {
    expect(isExternalSystemSlug('sap_s4')).toBe(true)
    expect(isExternalSystemSlug('custom:oracle_ebs')).toBe(true)
    expect(isExternalSystemSlug('SAP')).toBe(false)
    expect(isExternalSystemSlug('a:b:c')).toBe(false)
    expect(isExternalSystemSlug('')).toBe(false)
  })

  it('round-trips external refs, empty optional parts becoming null', () => {
    const drafts = externalRefDrafts([{ system: 'sap_s4', type: 'supplier', id: '100045', number: null, url: null }])
    expect(drafts[0]).toMatchObject({ system: 'sap_s4', number: '', url: '' })
    expect(externalRefsPayload([{ ...drafts[0]!, id: ' 100045 ', number: ' ' }])).toEqual([
      { system: 'sap_s4', type: 'supplier', id: '100045', number: null, url: null },
    ])
  })

  it('knows the import row codes of ARCHITECTURE §14.7', () => {
    expect(isKnownImportRowCode('unknown_region')).toBe(true)
    expect(isKnownImportRowCode('something_else')).toBe(false)
  })
})
