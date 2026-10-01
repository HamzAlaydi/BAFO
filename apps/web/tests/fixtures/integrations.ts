/**
 * Integrations fixtures shaped exactly like API.md §2.12 (for mocked services in tests).
 */
import type { ApiClient, ApiKey, ExportJob, ImportJob, Vendor, WebhookDelivery, WebhookEndpoint } from '../../app/types/api/integrations'

export function makeApiKey(overrides: Partial<ApiKey> = {}): ApiKey {
  return {
    id: '01j9key0000000000000000001',
    prefix: 'ab12cd34',
    masked: 'bafo_test_ab12cd34_…WXYZ',
    expires_at: '2099-10-01T00:00:00.000Z',
    revoked_at: null,
    last_used_at: null,
    created_at: '2026-10-01T09:00:00.000Z',
    ...overrides,
  }
}

export function makeApiClient(overrides: Partial<ApiClient> = {}): ApiClient {
  return {
    id: '01j9cli0000000000000000001',
    client_id: '01j9cli0000000000000000001',
    name: 'SAP CPI – PROD',
    description: null,
    scopes: ['competitions:read', 'vendors:write'],
    status: 'active',
    keys: [makeApiKey()],
    last_used_at: null,
    created_by: { id: '01j9zq4m1x2a3b4c5d6e7f8g9h', name: 'سارة العتيبي' },
    created_at: '2026-10-01T09:00:00.000Z',
    ...overrides,
  }
}

export function makeWebhookEndpoint(overrides: Partial<WebhookEndpoint> = {}): WebhookEndpoint {
  return {
    id: '01j9whe0000000000000000001',
    url: 'https://erp.example.sa/bafo/webhooks',
    description: null,
    event_types: ['award.issued', 'competition.closed'],
    status: 'active',
    disabled_reason: null,
    failing_since: null,
    last_success_at: null,
    last_failure_at: null,
    created_at: '2026-10-01T09:00:00.000Z',
    ...overrides,
  }
}

export function makeDelivery(overrides: Partial<WebhookDelivery> = {}): WebhookDelivery {
  return {
    id: '01j9del0000000000000000001',
    event: { id: '01jf3m6k8q9r0s1t2v3w4x5y6z', type: 'award.issued', occurred_at: '2026-10-01T09:00:00.000Z' },
    status: 'failed',
    attempts: 9,
    next_attempt_at: null,
    last_attempt_at: '2026-10-02T09:00:00.000Z',
    last_http_status: 500,
    last_error: 'HTTP 500',
    last_response_excerpt: '{"error":"boom"}',
    last_duration_ms: 812,
    succeeded_at: null,
    failed_at: '2026-10-02T09:00:00.000Z',
    ...overrides,
  }
}

export function makeImportJob(overrides: Partial<ImportJob> = {}): ImportJob {
  return {
    id: '01j9imp0000000000000000001',
    type: 'vendors',
    mode: 'validate',
    status: 'queued',
    total_rows: null,
    valid_rows: null,
    created_rows: null,
    updated_rows: null,
    error_rows: null,
    errors_preview: [],
    errors_file: null,
    source_file: null,
    failure_message: null,
    finished_at: null,
    created_at: '2026-10-01T09:00:00.000Z',
    ...overrides,
  }
}

export function makeExportJob(overrides: Partial<ExportJob> = {}): ExportJob {
  return {
    id: '01j9exp0000000000000000001',
    type: 'vendors',
    format: 'xlsx',
    filters: {},
    status: 'queued',
    row_count: null,
    file: null,
    failure_message: null,
    finished_at: null,
    created_at: '2026-10-01T09:00:00.000Z',
    ...overrides,
  }
}

export function makeVendor(overrides: Partial<Vendor> = {}): Vendor {
  return {
    id: '01j9ven0000000000000000001',
    name: 'شركة الريادة',
    name_en: 'Al Riyada Co.',
    cr_number: '1010987654',
    vat_number: '300000000000013',
    email: 'sales@riyada.sa',
    contact_name: 'Khalid',
    phone: '+966551234567',
    region: { id: '01j9reg0000000000000000000', code: 'RIY', name: 'الرياض' },
    city: 'الرياض',
    categories: [],
    status: 'active',
    linked_organization: null,
    source: 'web',
    notes: null,
    external_refs: [{ system: 'sap_s4', type: 'supplier', id: '100045', number: null, url: null }],
    created_at: '2026-10-01T09:00:00.000Z',
    updated_at: '2026-10-01T09:00:00.000Z',
    ...overrides,
  }
}
