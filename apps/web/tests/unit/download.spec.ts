import { describe, expect, it } from 'vitest'
import { filenameFromDisposition, resolveApiUrl, sanitizeFilename } from '~/utils/download'

const BASE = 'http://localhost:8000/api/app/v1'

describe('resolveApiUrl (API.md §0.7)', () => {
  it('resolves File.download_path against the API origin', () => {
    expect(resolveApiUrl('/api/app/v1/files/01j/download', BASE)).toBe('http://localhost:8000/api/app/v1/files/01j/download')
  })

  it('resolves module paths against the API base', () => {
    expect(resolveApiUrl('/billing/invoices/01j/pdf', BASE)).toBe('http://localhost:8000/api/app/v1/billing/invoices/01j/pdf')
  })

  it('accepts absolute URLs on the API origin only, so the bearer never leaves it', () => {
    expect(resolveApiUrl('http://localhost:8000/api/app/v1/files/x/download', BASE)).toBe('http://localhost:8000/api/app/v1/files/x/download')
    expect(resolveApiUrl('https://evil.example/steal', BASE)).toBeNull()
    expect(resolveApiUrl('//evil.example/steal', BASE)).toBeNull()
    expect(resolveApiUrl('relative/path', BASE)).toBeNull()
    expect(resolveApiUrl('/x', 'not a url')).toBeNull()
  })
})

describe('filenameFromDisposition', () => {
  it('prefers the RFC 5987 parameter', () => {
    expect(filenameFromDisposition(`attachment; filename="report.pdf"; filename*=UTF-8''%D8%AA%D9%82%D8%B1%D9%8A%D8%B1.pdf`)).toBe('تقرير.pdf')
  })

  it('reads plain file names', () => {
    expect(filenameFromDisposition('attachment; filename="BAFO-INV-2026-000042.pdf"')).toBe('BAFO-INV-2026-000042.pdf')
    expect(filenameFromDisposition('attachment; filename=specs.pdf')).toBe('specs.pdf')
    expect(filenameFromDisposition(null)).toBeNull()
    expect(filenameFromDisposition('inline')).toBeNull()
  })

  it('strips path separators and control characters', () => {
    expect(filenameFromDisposition('attachment; filename="../../etc/passwd"')).toBe('.._.._etc_passwd')
    expect(sanitizeFilename('..')).toBe('download')
    expect(sanitizeFilename('a\u0000b')).toBe('a_b')
  })
})
