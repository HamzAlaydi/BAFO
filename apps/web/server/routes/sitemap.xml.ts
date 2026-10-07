/**
 * `/sitemap.xml` (RELEASE_SCOPE §6.3): the two locale roots plus every legal page that
 * `GET /legal/{code}` answers 200 for, per locale (`Accept-Language`), with `lastmod` from
 * `published_at` and `xhtml:link` alternates. The API is asked at most once an hour; when it fails
 * the locale roots alone are listed.
 */
interface LegalDocumentResponse {
  data?: { published_at?: string }
}

export default defineCachedEventHandler(async (event) => {
  const config = useRuntimeConfig(event)
  const siteUrl = String(config.public.siteUrl)
  const apiBase = String(config.public.apiBase).replace(/\/+$/, '')

  const publications: Partial<Record<LegalCode, LegalPublication>> = {}
  await Promise.all(LEGAL_CODES.flatMap(code => SITE_LOCALES.map(async (locale) => {
    try {
      const response = await $fetch<LegalDocumentResponse>(`${apiBase}/legal/${code}`, {
        headers: { 'Accept': 'application/json', 'Accept-Language': locale, 'X-Platform': 'web' },
        timeout: 5_000,
        retry: 0,
      })
      const publishedAt = response.data?.published_at
      if (publishedAt) (publications[code] ??= {})[locale] = publishedAt
    }
    catch {
      // 404 (no published version) or an API failure: the page is simply not listed for this locale.
    }
  })))

  setHeader(event, 'Content-Type', 'application/xml; charset=utf-8')
  return buildSitemapXml(siteUrl, [...rootSitemapEntries(), ...legalSitemapEntries(publications)])
}, { name: 'sitemap', maxAge: 60 * 60, swr: true, getKey: () => 'sitemap' })
