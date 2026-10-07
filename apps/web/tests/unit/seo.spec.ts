import { readFileSync } from 'node:fs'
import { describe, expect, it } from 'vitest'
import { buildLandingJsonLd, landingOgImageUrl, landingPageUrl, LANDING_OG_IMAGE } from '~/utils/landing-seo'
import { buildRobotsTxt, buildSitemapXml, legalSitemapEntries, rootSitemapEntries } from '../../server/utils/seo'

/** PNG header: 8-byte signature, then the IHDR chunk with width and height as big-endian uint32. */
function pngSize(path: string): { width: number, height: number } {
  const bytes = readFileSync(path)
  expect(bytes.subarray(0, 8).toString('hex')).toBe('89504e470d0a1a0a')
  expect(bytes.subarray(12, 16).toString('ascii')).toBe('IHDR')
  return { width: bytes.readUInt32BE(16), height: bytes.readUInt32BE(20) }
}

describe('landing SEO (RELEASE_SCOPE §6.3)', () => {
  it('ships 1200×630 Open Graph images for both locales', () => {
    for (const locale of ['ar', 'en']) {
      const path = new URL(`../../public/og/bafo-og-${locale}.png`, import.meta.url).pathname
      expect(pngSize(path), locale).toEqual({ width: LANDING_OG_IMAGE.width, height: LANDING_OG_IMAGE.height })
    }
  })

  it('builds absolute page and image URLs without a double slash', () => {
    expect(landingPageUrl('https://bafo.example/', 'ar')).toBe('https://bafo.example/ar')
    expect(landingOgImageUrl('https://bafo.example', 'en')).toBe('https://bafo.example/og/bafo-og-en.png')
  })

  it('emits Organization, WebSite, SoftwareApplication and FAQPage from the rendered items', () => {
    const faq = [{ question: 'ما هو بافو؟', answer: 'منصة.' }, { question: 'كيف؟', answer: 'هكذا.' }]
    const graph = buildLandingJsonLd({ siteUrl: 'https://bafo.example', locale: 'ar', name: 'بافو', tagline: 'أفضل عرض نهائي', description: 'وصف', faq })
    expect(graph.map(node => node['@type'])).toEqual(['Organization', 'WebSite', 'SoftwareApplication', 'FAQPage'])
    expect(graph[0]).toMatchObject({ url: 'https://bafo.example/ar', logo: 'https://bafo.example/brand/mark-color-1024.png', alternateName: 'BAFO' })
    expect(graph[1]).toMatchObject({ inLanguage: 'ar-SA' })
    expect(graph[2]).toMatchObject({ applicationCategory: 'BusinessApplication', image: 'https://bafo.example/og/bafo-og-ar.png' })
    const page = graph[3] as { mainEntity: Array<{ name: string, acceptedAnswer: { text: string } }> }
    expect(page.mainEntity).toHaveLength(2)
    expect(page.mainEntity[1]).toEqual({ '@type': 'Question', 'name': 'كيف؟', 'acceptedAnswer': { '@type': 'Answer', 'text': 'هكذا.' } })
    expect(JSON.stringify(graph)).not.toContain('</script')
  })
})

describe('sitemap.xml builder', () => {
  it('lists the locale roots with ar, en and x-default alternates', () => {
    const xml = buildSitemapXml('https://bafo.example/', rootSitemapEntries())
    expect(xml.startsWith('<?xml version="1.0" encoding="UTF-8"?>')).toBe(true)
    expect(xml).toContain('xmlns:xhtml="http://www.w3.org/1999/xhtml"')
    expect(xml.match(/<url>/g)).toHaveLength(2)
    expect(xml).toContain('<loc>https://bafo.example/ar</loc>')
    expect(xml).toContain('<loc>https://bafo.example/en</loc>')
    expect(xml).toContain('<xhtml:link rel="alternate" hreflang="x-default" href="https://bafo.example/ar"/>')
    expect(xml).toContain('<xhtml:link rel="alternate" hreflang="en" href="https://bafo.example/en"/>')
  })

  it('adds a legal page per published locale, with lastmod and alternates, and skips unpublished codes', () => {
    const entries = legalSitemapEntries({
      terms: { ar: '2026-09-29T17:47:10.000Z', en: '2026-09-29T17:47:10.000Z' },
      refund: { ar: '2026-09-29T17:47:10.000Z', en: null },
      api_terms: {},
    })
    expect(entries.map(entry => entry.path)).toEqual(['/ar/legal/terms', '/en/legal/terms', '/ar/legal/refund'])
    expect(entries[0]!.alternates.map(alternate => alternate.hreflang)).toEqual(['ar', 'en', 'x-default'])
    // A document published in Arabic only still gets its x-default, and no English alternate.
    expect(entries[2]!.alternates).toEqual([
      { hreflang: 'ar', path: '/ar/legal/refund' },
      { hreflang: 'x-default', path: '/ar/legal/refund' },
    ])
    const xml = buildSitemapXml('https://bafo.example', entries)
    expect(xml).toContain('<lastmod>2026-09-29T17:47:10.000Z</lastmod>')
  })

  it('escapes XML special characters in URLs', () => {
    const xml = buildSitemapXml('https://bafo.example', [{ path: '/ar?a=1&b=2', alternates: [] }])
    expect(xml).toContain('<loc>https://bafo.example/ar?a=1&amp;b=2</loc>')
  })
})

describe('robots.txt builder', () => {
  it('allows the public pages, blocks the private areas in both locales and announces the sitemap', () => {
    const text = buildRobotsTxt('https://bafo.example/')
    expect(text.split('\n').slice(0, 2)).toEqual(['User-agent: *', 'Allow: /'])
    for (const area of ['dashboard', 'auth', 'invitations']) {
      expect(text).toContain(`Disallow: /ar/${area}`)
      expect(text).toContain(`Disallow: /en/${area}`)
    }
    expect(text.trimEnd().endsWith('Sitemap: https://bafo.example/sitemap.xml')).toBe(true)
  })
})
