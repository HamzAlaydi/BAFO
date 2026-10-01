<script setup lang="ts">
import { ChevronDown, LogOut, UserRound } from '@lucide/vue'
import type { MenuEntry } from '~/types/ui'

/** Account menu (SCREENS §2.3): Account and Sign out. */
const { t } = useI18n()
const auth = useAuthStore()
const localePath = useLocalePath()

const items = computed<MenuEntry[]>(() => [
  { key: 'account', label: t('nav.account'), icon: UserRound, to: '/dashboard/account' },
  { type: 'separator', key: 'sep' },
  { key: 'logout', label: t('nav.sign_out'), icon: LogOut },
])

async function onSelect(key: string): Promise<void> {
  if (key === 'logout') {
    await auth.logout()
    await navigateTo(localePath('/auth/login'))
  }
}
</script>

<template>
  <UiDropdownMenu
    :label="t('nav.user_menu')"
    :items="items"
    trigger-class="gap-2 px-1 py-1 hover:bg-surface-muted"
    @select="onSelect"
  >
    <template #trigger>
      <UiAvatar
        :name="auth.user?.name ?? ''"
        :src="auth.user?.avatar_url"
        size="sm"
      />
      <span class="sr-only lg:hidden">{{ t('nav.user_menu') }}</span>
      <span class="hidden max-w-[10rem] truncate text-sm font-semibold text-fg lg:block">{{ auth.user?.name }}</span>
      <ChevronDown
        :size="16"
        class="hidden text-fg-muted lg:block"
        aria-hidden="true"
      />
    </template>
    <template #header>
      <div
        v-if="auth.user"
        class="border-b border-line px-3.5 py-3"
      >
        <p class="truncate text-sm font-bold text-fg">
          {{ auth.user.name }}
        </p>
        <p class="truncate text-xs text-fg-muted">
          <bdi>{{ auth.user.email }}</bdi>
        </p>
      </div>
    </template>
  </UiDropdownMenu>
</template>
