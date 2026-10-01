<script setup lang="ts">
import { Languages } from '@lucide/vue'

/** Links to the same page in the other language (the page itself, not a JS toggle, so it works without JS). */
withDefaults(defineProps<{ compact?: boolean }>(), { compact: false })

const { locale, locales, t } = useI18n()
const switchLocalePath = useSwitchLocalePath()
const target = computed(() => locales.value.find(entry => entry.code !== locale.value))
</script>

<template>
  <NuxtLink
    v-if="target"
    :to="switchLocalePath(target.code)"
    :lang="target.language"
    :hreflang="target.language"
    :aria-label="t('common.lang.switch_to', { language: target.name })"
    class="inline-flex h-10 items-center gap-2 rounded-md px-2.5 text-sm font-semibold text-fg-muted transition-colors hover:bg-surface-muted hover:text-fg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
  >
    <Languages
      :size="18"
      aria-hidden="true"
    />
    <span :class="compact && 'sr-only'">{{ target.name }}</span>
  </NuxtLink>
</template>
