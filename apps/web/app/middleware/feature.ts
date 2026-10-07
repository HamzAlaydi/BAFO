/**
 * Pages hidden by the release scope (RELEASE_SCOPE.md §5): `definePageMeta({ middleware: ['auth', 'feature'],
 * feature: 'team_management' })`. When none of the page's flags is on, a direct URL renders the 404 state
 * in place (the dashboard layout shows `UiNotFoundState`; same wording as a 404, no redirect). A page with
 * `featureFallback` (the invoice pages, whose notification deep link lands on W30, §10) sends the visitor
 * there instead, with a notice. Nothing is deleted: the page and its routes stay, and come back as soon as
 * the admin switches the scope to `full`.
 *
 * The flags come from `GET /app-config`; the middleware waits for that one request so a direct link is
 * judged on the server's map, not on the defaults. When the config cannot be loaded at all, the page is
 * left to render its own error state.
 */
export default defineNuxtRouteMiddleware(async (to) => {
  const needed = to.meta.feature
  if (!needed) return
  const appConfig = useAppConfigStore()
  await appConfig.load()
  if (!appConfig.loaded) return
  if (anyFeatureEnabled(appConfig.flags, needed)) return
  if (typeof to.meta.featureFallback !== 'string') return

  const { t } = useNuxtApp().$i18n
  useToast().info(t('errors.feature_disabled'))
  return navigateTo(useLocalePath()(to.meta.featureFallback), { replace: true })
})
