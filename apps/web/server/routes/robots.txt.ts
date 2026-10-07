/**
 * `/robots.txt` (RELEASE_SCOPE §6.3): everything public is crawlable, the dashboard, sign-in and the
 * invitation landing are not, and the sitemap is announced with the configured site URL.
 */
export default defineEventHandler((event) => {
  const siteUrl = String(useRuntimeConfig(event).public.siteUrl)
  setHeader(event, 'Content-Type', 'text/plain; charset=utf-8')
  setHeader(event, 'Cache-Control', 'public, max-age=3600')
  return buildRobotsTxt(siteUrl)
})
