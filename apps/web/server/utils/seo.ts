/**
 * Pure builders for the public SEO routes (RELEASE_SCOPE §6.3): `/sitemap.xml` and `/robots.txt`.
 * No Nitro globals here, so the unit tests cover them directly.
 */
export const SITE_LOCALES = ['ar', 'en'] as const
export type SiteLocale = (typeof SITE_LOCALES)[number]

/** The default locale is `ar` (nuxt.config `i18n.defaultLocale`), so `x-default` points at `/ar/...`. */
export const DEFAULT_SITE_LOCALE: SiteLocale = 'ar'

export const LEGAL_CODES = ['terms', 'privacy', 'refund', 'competition_rules', 'api_terms'] as const
export type LegalCode = (typeof LEGAL_CODES)[number]

/** Private areas never listed or crawled (the dashboard, sign-in and the invitation landing). */
export const PRIVATE_PATHS = ['dashboard', 'auth', 'invitations'] as const

export interface SitemapAlternate {
  hreflang: string
  path: string
}

export interface SitemapEntry {
  /** Locale-prefixed path starting with `/`, e.g. `/ar/legal/terms`. */
  path: string
  /** ISO-8601 date-time, when known. */
  lastmod?: string
  alternates: SitemapAlternate[]
}

/** A legal document's `published_at` per locale, `null` when that locale answered 404. */
export type LegalPublication = Partial<Record<SiteLocale, string | null>>

export function normalizeSiteUrl(siteUrl: string): string {
  return siteUrl.replace(/\/+$/, '')
}

function escapeXml(value: string): string {
  return value
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&apos;')
}

function alternatesFor(paths: Partial<Record<SiteLocale, string>>): SitemapAlternate[] {
  const alternates: SitemapAlternate[] = []
  for (const locale of SITE_LOCALES) {
    const path = paths[locale]
    if (path) alternates.push({ hreflang: locale, path })
  }
  const fallback = paths[DEFAULT_SITE_LOCALE] ?? alternates[0]?.path
  if (fallback) alternates.push({ hreflang: 'x-default', path: fallback })
  return alternates
}

/** `/ar` and `/en` with their alternates. */
export function rootSitemapEntries(): SitemapEntry[] {
  const paths = Object.fromEntries(SITE_LOCALES.map(locale => [locale, `/${locale}`])) as Record<SiteLocale, string>
  const alternates = alternatesFor(paths)
  return SITE_LOCALES.map(locale => ({ path: paths[locale], alternates }))
}

/**
 * One entry per published legal page and locale; a document missing in every locale is skipped.
 * `lastmod` is that locale's `published_at`.
 */
export function legalSitemapEntries(publications: Partial<Record<LegalCode, LegalPublication>>): SitemapEntry[] {
  const entries: SitemapEntry[] = []
  for (const code of LEGAL_CODES) {
    const publication = publications[code]
    if (!publication) continue
    const paths: Partial<Record<SiteLocale, string>> = {}
    for (const locale of SITE_LOCALES) {
      if (publication[locale]) paths[locale] = `/${locale}/legal/${code}`
    }
    const alternates = alternatesFor(paths)
    for (const locale of SITE_LOCALES) {
      const path = paths[locale]
      const lastmod = publication[locale]
      if (path && lastmod) entries.push({ path, lastmod, alternates })
    }
  }
  return entries
}

export function buildSitemapXml(siteUrl: string, entries: SitemapEntry[]): string {
  const base = normalizeSiteUrl(siteUrl)
  const urls = entries.map((entry) => {
    const lines = [`    <loc>${escapeXml(base + entry.path)}</loc>`]
    if (entry.lastmod) lines.push(`    <lastmod>${escapeXml(entry.lastmod)}</lastmod>`)
    for (const alternate of entry.alternates) {
      lines.push(`    <xhtml:link rel="alternate" hreflang="${escapeXml(alternate.hreflang)}" href="${escapeXml(base + alternate.path)}"/>`)
    }
    return `  <url>\n${lines.join('\n')}\n  </url>`
  })
  return [
    '<?xml version="1.0" encoding="UTF-8"?>',
    '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">',
    ...urls,
    '</urlset>',
    '',
  ].join('\n')
}

export function buildRobotsTxt(siteUrl: string): string {
  const base = normalizeSiteUrl(siteUrl)
  const disallow = PRIVATE_PATHS.flatMap(area => SITE_LOCALES.map(locale => `Disallow: /${locale}/${area}`))
  return ['User-agent: *', 'Allow: /', ...disallow, '', `Sitemap: ${base}/sitemap.xml`, ''].join('\n')
}
