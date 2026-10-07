<script setup lang="ts">
/**
 * Landing header (W01): lockup, in-page section links (from `lg`), language switch, the theme menu
 * only while `dark_mode` is on, and the sign-in / create-account pair (or Dashboard with a session).
 * Section links are plain anchors so they work without JavaScript.
 */
const props = withDefaults(defineProps<{
  darkMode?: boolean
  /** The plans section rendered (it is hidden when `GET /plans` fails). */
  showPlans?: boolean
}>(), {
  darkMode: false,
  showPlans: false,
})

const { t } = useI18n()
const auth = useAuthStore()

const links = computed(() => [
  { id: 'how-it-works', label: t('landing.nav.how') },
  { id: 'modes', label: t('landing.nav.modes') },
  { id: 'fairness', label: t('landing.nav.fairness') },
  ...(props.showPlans ? [{ id: 'plans', label: t('landing.nav.plans') }] : []),
  { id: 'faq', label: t('landing.nav.faq') },
])
</script>

<template>
  <header class="sticky top-0 z-30 border-b border-line bg-page/90 backdrop-blur">
    <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6">
      <NuxtLinkLocale
        to="/"
        class="rounded-md focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ring"
      >
        <AppLogo size="sm" />
      </NuxtLinkLocale>

      <nav
        :aria-label="t('landing.nav.label')"
        class="hidden lg:block"
      >
        <ul class="flex items-center gap-1 text-sm font-semibold text-fg-muted">
          <li
            v-for="link in links"
            :key="link.id"
          >
            <a
              :href="`#${link.id}`"
              class="inline-flex h-10 items-center rounded-md px-3 transition-colors hover:bg-surface-muted hover:text-fg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
            >{{ link.label }}</a>
          </li>
        </ul>
      </nav>

      <div class="flex items-center gap-1 sm:gap-2">
        <AppLanguageSwitch compact />
        <AppThemeMenu v-if="darkMode" />
        <template v-if="auth.isAuthenticated">
          <UiButton
            to="/dashboard"
            size="sm"
          >
            {{ t('nav.dashboard') }}
          </UiButton>
        </template>
        <template v-else>
          <!-- Below 400 px only "Create account" fits next to the logo; the register page links to sign-in. -->
          <span class="hidden xs:contents">
            <UiButton
              to="/auth/login"
              variant="ghost"
              size="sm"
            >
              {{ t('nav.login') }}
            </UiButton>
          </span>
          <UiButton
            to="/auth/register"
            size="sm"
          >
            {{ t('nav.register') }}
          </UiButton>
        </template>
      </div>
    </div>
  </header>
</template>
