import { describe, expect, it } from 'vitest'
import { cleanQuery, multipart, pageFrom } from '~/services/http'

describe('services/http helpers', () => {
  it('cleans query values the way the API expects', () => {
    expect(cleanQuery({ role: 'issuer', q: '', status: ['live', 'closed'], unread: true, archived: false, page: 2, empty: [], none: null, missing: undefined }))
      .toEqual({ role: 'issuer', status: 'live,closed', unread: 1, archived: 0, page: 2 })
    expect(cleanQuery({ q: '' })).toBeUndefined()
    expect(cleanQuery(undefined)).toBeUndefined()
  })

  it('reads page pagination, with a single-page default', () => {
    const pagination = { type: 'page' as const, current_page: 2, per_page: 20, has_more: true, total: 57, last_page: 3 }
    expect(pageFrom({ data: [1, 2], meta: { pagination } })).toEqual({ items: [1, 2], pagination })
    expect(pageFrom({ data: [1] })).toEqual({ items: [1], pagination: { type: 'page', current_page: 1, per_page: 1, has_more: false, total: 1, last_page: 1 } })
  })

  it('builds multipart bodies with the file part and plain fields', () => {
    const form = multipart({ file: new Blob(['x']), kind: 'document', title: null, sponsored: true })
    expect(form.get('kind')).toBe('document')
    expect(form.get('sponsored')).toBe('1')
    expect(form.has('title')).toBe(false)
    expect(form.get('file')).toBeInstanceOf(Blob)
  })
})
