import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { makeTokenPayload } from '../fixtures/api'
import { uploadAttachmentWithProgress } from '~/services/competitions'

/** A scripted XMLHttpRequest: records the request and lets the test drive progress and the response. */
class FakeXhr {
  static last: FakeXhr | null = null
  method = ''
  url = ''
  headers: Record<string, string> = {}
  body: unknown = null
  status = 0
  responseText = ''
  responseHeaders: Record<string, string> = {}
  upload: { onprogress: ((event: { loaded: number, total: number, lengthComputable: boolean }) => void) | null } = { onprogress: null }
  onload: (() => void) | null = null
  onerror: (() => void) | null = null
  onabort: (() => void) | null = null

  constructor() {
    FakeXhr.last = this
  }

  open(method: string, url: string): void {
    this.method = method
    this.url = url
  }

  setRequestHeader(name: string, value: string): void {
    this.headers[name] = value
  }

  getResponseHeader(name: string): string | null {
    return this.responseHeaders[name.toLowerCase()] ?? null
  }

  send(body: unknown): void {
    this.body = body
  }

  abort(): void {
    this.onabort?.()
  }

  respond(status: number, body: unknown): void {
    this.status = status
    this.responseText = JSON.stringify(body)
    this.onload?.()
  }
}

beforeEach(() => {
  vi.stubGlobal('XMLHttpRequest', FakeXhr)
  useAuthStore().setSession(makeTokenPayload({}, 'secret-token'))
})

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('attachment upload with progress', () => {
  it('sends the multipart body with the API headers, reports progress and resolves the attachment', async () => {
    const progress: Array<{ loaded: number, total: number | null }> = []
    const file = new Blob(['%PDF'], { type: 'application/pdf' })
    const pending = uploadAttachmentWithProgress('01j9comp', { file, kind: 'document', title: 'كراسة' }, value => progress.push(value))
    const xhr = FakeXhr.last!

    expect(xhr.method).toBe('POST')
    expect(xhr.url).toMatch(/\/competitions\/01j9comp\/attachments$/)
    expect(xhr.headers).toMatchObject({ 'Accept': 'application/json', 'Accept-Language': 'ar', 'X-Platform': 'web', 'Authorization': 'Bearer secret-token' })
    expect(xhr.headers['X-Request-Id']).toMatch(/^[0-9a-f]{32}$/)
    const form = xhr.body as FormData
    expect(form.get('kind')).toBe('document')
    expect(form.get('title')).toBe('كراسة')
    expect(form.get('file')).toBeInstanceOf(Blob)

    xhr.upload.onprogress?.({ loaded: 50, total: 100, lengthComputable: true })
    xhr.upload.onprogress?.({ loaded: 80, total: 0, lengthComputable: false })
    expect(progress).toEqual([{ loaded: 50, total: 100 }, { loaded: 80, total: null }])

    xhr.respond(201, { data: { id: 'att1', kind: 'document' }, meta: { server_time: new Date().toISOString() } })
    await expect(pending).resolves.toMatchObject({ id: 'att1' })
  })

  it('rejects with the normalised API error (file_too_large)', async () => {
    const pending = uploadAttachmentWithProgress('01j9comp', { file: new Blob(['x']), kind: 'document' }, () => {})
    FakeXhr.last!.respond(422, { message: 'too big', code: 'file_too_large', errors: { file: ['too big'] } })
    await expect(pending).rejects.toMatchObject({ status: 422, code: 'file_too_large' })
  })

  it('rejects with a network error when the request fails', async () => {
    const pending = uploadAttachmentWithProgress('01j9comp', { file: new Blob(['x']), kind: 'invitation_document' }, () => {})
    FakeXhr.last!.onerror?.()
    await expect(pending).rejects.toMatchObject({ code: 'network_error', status: null })
  })

  it('ends the session on 401', async () => {
    const pending = uploadAttachmentWithProgress('01j9comp', { file: new Blob(['x']), kind: 'document' }, () => {})
    FakeXhr.last!.respond(401, { message: 'no', code: 'unauthenticated', errors: {} })
    await expect(pending).rejects.toMatchObject({ status: 401 })
    expect(useAuthStore().isAuthenticated).toBe(false)
    expect(useAuthStore().endedReason).toBe('expired')
  })
})
