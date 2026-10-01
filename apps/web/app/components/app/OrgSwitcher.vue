<script setup lang="ts">
import { BadgeCheck } from '@lucide/vue'

/**
 * The user's organisation in the top bar (one membership per user in v1, ARCHITECTURE §5.3):
 * logo, name and the verified badge, linking to the organisation page.
 */
const { t } = useI18n()
const auth = useAuthStore()
</script>

<template>
  <NuxtLinkLocale
    to="/dashboard/organization"
    class="flex max-w-[16rem] min-w-0 items-center gap-2.5 rounded-md px-1.5 py-1 text-start hover:bg-surface-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
    :aria-label="t('nav.organization_link', { name: auth.organization?.name ?? '' })"
  >
    <UiOrgLogo
      :name="auth.organization?.name"
      :src="auth.organization?.logo_url"
      size="sm"
    />
    <span class="hidden min-w-0 flex-col sm:flex">
      <span class="text-xs text-fg-muted">{{ t('nav.organization') }}</span>
      <span class="flex min-w-0 items-center gap-1">
        <span class="truncate text-sm font-bold text-fg">{{ auth.organization?.name ?? '—' }}</span>
        <BadgeCheck
          v-if="auth.organization?.verified"
          :size="14"
          class="shrink-0 text-brand"
          :aria-label="t('organization.verified')"
          role="img"
        />
      </span>
    </span>
  </NuxtLinkLocale>
</template>
