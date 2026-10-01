/**
 * Integrations dashboard endpoints (API.md §1.9): vendors (`competitions.create`), import and export
 * (`integrations.manage`), and API clients, keys and webhooks (`integrations.manage` + `api_enabled`).
 * Secrets in create/rotate responses are shown once: never store or log them.
 */
import type { Page } from '~/types/api/common'
import type {
  ApiClient,
  ApiClientInput,
  ApiClientWithSecret,
  ApiKeyWithSecret,
  CreateExportRequest,
  DeliveryListQuery,
  ExportFormat,
  ExportJob,
  ImportJob,
  Vendor,
  VendorInput,
  VendorListQuery,
  WebhookDelivery,
  WebhookEndpoint,
  WebhookEndpointInput,
  WebhookEndpointUpdate,
  WebhookEndpointWithSecret,
  WebhookEventType,
} from '~/types/api/integrations'
import { getData, getPage, multipart, send, sendData, seg } from './http'

// ---------- Vendors ----------

export function listVendors(query: VendorListQuery = {}): Promise<Page<Vendor>> {
  return getPage<Vendor>('/vendors', { ...query, q: query.q?.trim(), per_page: query.per_page ?? 20 })
}

/** `vendor_email_taken` / `external_ref_conflict` (409, `details.existing_id`). */
export function createVendor(body: VendorInput): Promise<Vendor> {
  return sendData<Vendor>('POST', '/vendors', body)
}

export function fetchVendor(id: string): Promise<Vendor> {
  return getData<Vendor>(`/vendors/${seg(id)}`)
}

/** `external_refs` replaces the whole list. */
export function updateVendor(id: string, body: Partial<VendorInput>): Promise<Vendor> {
  return sendData<Vendor>('PATCH', `/vendors/${seg(id)}`, body)
}

/** Archives (200 `Vendor` with status `archived`). */
export function archiveVendor(id: string): Promise<Vendor> {
  return sendData<Vendor>('DELETE', `/vendors/${seg(id)}`)
}

// ---------- API clients and keys ----------

export function listApiClients(): Promise<ApiClient[]> {
  return getData<ApiClient[]>('/integrations/api-clients')
}

/** 201 with `client_secret` (shown once). */
export function createApiClient(body: ApiClientInput): Promise<ApiClientWithSecret> {
  return sendData<ApiClientWithSecret>('POST', '/integrations/api-clients', body)
}

export function fetchApiClient(id: string): Promise<ApiClient> {
  return getData<ApiClient>(`/integrations/api-clients/${seg(id)}`)
}

export function updateApiClient(id: string, body: Partial<ApiClientInput>): Promise<ApiClient> {
  return sendData<ApiClient>('PATCH', `/integrations/api-clients/${seg(id)}`, body)
}

export function revokeApiClient(id: string): Promise<void> {
  return send('DELETE', `/integrations/api-clients/${seg(id)}`)
}

export function rotateApiClientSecret(id: string): Promise<ApiClientWithSecret> {
  return sendData<ApiClientWithSecret>('POST', `/integrations/api-clients/${seg(id)}/rotate-secret`)
}

/** 201 with `key` (shown once); `expires_in_days` 1–730, default 365. */
export function createApiKey(clientId: string, expiresInDays = 365): Promise<ApiKeyWithSecret> {
  return sendData<ApiKeyWithSecret>('POST', `/integrations/api-clients/${seg(clientId)}/keys`, { expires_in_days: expiresInDays })
}

export function revokeApiKey(clientId: string, keyId: string): Promise<void> {
  return send('DELETE', `/integrations/api-clients/${seg(clientId)}/keys/${seg(keyId)}`)
}

// ---------- Webhooks ----------

export function listWebhookEventTypes(): Promise<WebhookEventType[]> {
  return getData<WebhookEventType[]>('/integrations/webhook-event-types')
}

export function listWebhookEndpoints(): Promise<WebhookEndpoint[]> {
  return getData<WebhookEndpoint[]>('/integrations/webhook-endpoints')
}

/** 201 with `secret` (shown once); `webhook_url_invalid` (422). */
export function createWebhookEndpoint(body: WebhookEndpointInput): Promise<WebhookEndpointWithSecret> {
  return sendData<WebhookEndpointWithSecret>('POST', '/integrations/webhook-endpoints', body)
}

export function fetchWebhookEndpoint(id: string): Promise<WebhookEndpoint> {
  return getData<WebhookEndpoint>(`/integrations/webhook-endpoints/${seg(id)}`)
}

/** Re-enabling clears `failing_since`. */
export function updateWebhookEndpoint(id: string, body: WebhookEndpointUpdate): Promise<WebhookEndpoint> {
  return sendData<WebhookEndpoint>('PATCH', `/integrations/webhook-endpoints/${seg(id)}`, body)
}

export function deleteWebhookEndpoint(id: string): Promise<void> {
  return send('DELETE', `/integrations/webhook-endpoints/${seg(id)}`)
}

/** 202 `{event_id}`; refresh the deliveries after a few seconds. */
export function testWebhookEndpoint(id: string): Promise<{ event_id: string }> {
  return sendData<{ event_id: string }>('POST', `/integrations/webhook-endpoints/${seg(id)}/test`)
}

export function rotateWebhookSecret(id: string): Promise<WebhookEndpointWithSecret> {
  return sendData<WebhookEndpointWithSecret>('POST', `/integrations/webhook-endpoints/${seg(id)}/rotate-secret`)
}

export function listWebhookDeliveries(endpointId: string, query: DeliveryListQuery = {}): Promise<Page<WebhookDelivery>> {
  return getPage<WebhookDelivery>(`/integrations/webhook-endpoints/${seg(endpointId)}/deliveries`, { ...query, per_page: query.per_page ?? 20 })
}

export function redeliverWebhook(deliveryId: string): Promise<WebhookDelivery> {
  return sendData<WebhookDelivery>('POST', `/integrations/webhook-deliveries/${seg(deliveryId)}/redeliver`)
}

// ---------- Import and export ----------

/** API path of the vendor import template for `useFileDownload()`. */
export function importTemplatePath(format: ExportFormat, type: 'vendors' = 'vendors'): string {
  return `/integrations/imports/templates/${seg(type)}?format=${seg(format)}`
}

/** 202 `ImportJob`; poll `fetchImportJob` every 2 s (`useJobPoll`). */
export function createImportJob(file: Blob, mode: 'validate' | 'commit', type: 'vendors' = 'vendors'): Promise<ImportJob> {
  return sendData<ImportJob>('POST', '/integrations/imports', multipart({ file, type, mode }))
}

export function fetchImportJob(id: string): Promise<ImportJob> {
  return getData<ImportJob>(`/integrations/imports/${seg(id)}`)
}

/** 202 `ExportJob`; download `file` once `completed`. */
export function createExportJob(body: CreateExportRequest): Promise<ExportJob> {
  return sendData<ExportJob>('POST', '/integrations/exports', body)
}

export function fetchExportJob(id: string): Promise<ExportJob> {
  return getData<ExportJob>(`/integrations/exports/${seg(id)}`)
}
