<script setup lang="ts">
import { Menu } from '@lucide/vue'

/**
 * Dashboard frame (SCREENS §2.3): side navigation (start edge; a drawer below 1024 px), top bar
 * (organisation, bell, language, theme, account), global banners, and the session-wide page states
 * of S8 (session expired, account gate, e-mail not verified, offline). Subscribes to the private
 * user channel for the unread badge.
 */
const { t } = useI18n()
const auth = useAuthStore()
const home = useHomeStore()
const notifications = useNotificationsStore()
const features = useFeatures()
const route = useRoute()
const localePath = useLocalePath()
const toast = useToast()
const navOpen = ref(false)

// Private pages are never indexed (RELEASE_SCOPE.md §6.3).
useSeoMeta({ robots: 'noindex, nofollow' })

notifications.startRealtime()

// Home alerts power the global banners: loaded once per session (SCREENS §2.3).
onMounted(() => {
  if (auth.hasSession && !auth.gate) void home.ensureLoaded()
})

// Billing notifications change the alerts (renewals, trials, expiry).
notifications.onCreated((notification) => {
  if (/^(subscription|payment|voucher|invoice)\./.test(notification.type)) void home.load()
})

// The session can end mid-visit (sign-out in another tab, revoked token → 401): leave the dashboard.
watch(() => auth.isAuthenticated, (isAuthenticated) => {
  if (isAuthenticated) return
  if (auth.endedReason === 'expired') toast.warning(t('auth.session_expired'))
  void navigateTo(localePath({ path: '/auth/login', query: auth.endedReason === 'expired' ? { redirect: route.fullPath } : {} }))
})

// An unverified e-mail is finished on the OTP page (S8).
watch(() => auth.gate, (gate) => {
  if (gate === 'email_not_verified') {
    void navigateTo(localePath({ path: '/auth/verify', query: auth.user?.email ? { email: auth.user.email } : {} }))
  }
}, { immediate: true })

const blockingGate = computed(() => (auth.gate === 'account_inactive' || auth.gate === 'organization_suspended' ? auth.gate : null))

// A page hidden by the release scope renders the 404 state in place, without a redirect (RELEASE_SCOPE.md §5;
// `middleware/feature.ts` has already loaded the flags). The page itself is not mounted, so it calls no API.
// The router's current route, not `useRoute()`: Nuxt syncs the latter when a page finishes rendering, which a
// page that is not mounted never does, so leaving the hidden page would keep the 404 state on screen.
const router = useRouter()
const hiddenByScope = computed(() => features.ready.value && !features.anyEnabled(router.currentRoute.value.meta.feature))
</script>

<template>
  <div class="min-h-dvh bg-canvas lg:grid lg:grid-cols-[17rem_minmax(0,1fr)]">
    <!-- Sidebar (lg and up): the inline-start edge, i.e. the right side in Arabic -->
    <aside class="sticky top-0 hidden h-dvh flex-col gap-6 overflow-y-auto border-e border-line bg-surface px-3 py-5 lg:flex">
      <NuxtLinkLocale
        to="/dashboard"
        class="self-start rounded-md px-3 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
      >
        <AppLogo size="sm" />
      </NuxtLinkLocale>
      <AppSidebarNav v-if="!blockingGate" />
    </aside>

    <!-- Sidebar (below lg): drawer from the inline-start edge -->
    <UiDrawer
      v-model:open="navOpen"
      :title="t('nav.main')"
      side="start"
      size="sm"
      hide-header
    >
      <div class="flex flex-col gap-6 px-3 py-5">
        <div class="px-3">
          <AppLogo size="sm" />
        </div>
        <AppSidebarNav
          v-if="!blockingGate"
          @navigate="navOpen = false"
        />
      </div>
    </UiDrawer>

    <div class="flex min-w-0 flex-col">
      <header class="sticky top-0 z-30 flex h-16 items-center gap-2 border-b border-line bg-surface/95 px-3 backdrop-blur sm:px-6">
        <UiIconButton
          :icon="Menu"
          :label="t('nav.open_menu')"
          class="lg:hidden"
          @click="navOpen = true"
        />
        <AppOrgSwitcher />
        <div class="ms-auto flex items-center gap-0.5 sm:gap-1">
          <AppLanguageSwitch compact />
          <AppThemeMenu v-if="features.enabled('dark_mode')" />
          <AppNotificationsMenu v-if="!blockingGate" />
          <AppUserMenu />
        </div>
      </header>
      <AppOfflineBanner />
      <HomeAlertBanners v-if="!blockingGate" />
      <main
        id="main"
        class="flex-1 px-4 py-6 sm:px-6 lg:px-8"
      >
        <div class="mx-auto max-w-6xl">
          <AppAccountGate
            v-if="blockingGate"
            :gate="blockingGate"
          />
          <UiNotFoundState v-else-if="hiddenByScope" />
          <slot v-else />
        </div>
      </main>
    </div>
  </div>
</template>
