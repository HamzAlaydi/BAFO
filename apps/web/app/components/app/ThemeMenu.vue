<script setup lang="ts">
import type { Component } from 'vue'
import { Monitor, Moon, Sun } from '@lucide/vue'
import type { ThemePreference } from '~/composables/useTheme'
import type { MenuEntry } from '~/types/ui'

const { t } = useI18n()
const { preference, setPreference } = useTheme()

const icons: Record<ThemePreference, Component> = { system: Monitor, light: Sun, dark: Moon }

const items = computed<MenuEntry[]>(() => THEME_PREFERENCES.map(value => ({
  key: value,
  label: t(`common.theme.${value}`),
  icon: icons[value],
  checked: preference.value === value,
})))

function onSelect(key: string): void {
  setPreference(key as ThemePreference)
}
</script>

<template>
  <UiDropdownMenu
    :label="t('common.theme.label')"
    :items="items"
    trigger-class="size-10 justify-center text-fg-muted hover:bg-surface-muted hover:text-fg"
    @select="onSelect"
  >
    <template #trigger>
      <span class="sr-only">{{ t('common.theme.label') }}: {{ t(`common.theme.${preference}`) }}</span>
      <component
        :is="icons[preference]"
        :size="20"
        aria-hidden="true"
      />
    </template>
  </UiDropdownMenu>
</template>
