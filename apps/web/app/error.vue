<script setup lang="ts">
import type { NuxtError } from '#app'
import { SearchX, TriangleAlert } from '@lucide/vue'

/** W43 error page: 404 with Home, other errors with Retry and Home. The language switch stays. */
const props = defineProps<{ error: NuxtError }>()
const { t } = useI18n()
const localePath = useLocalePath()
const head = useLocaleHead({ dir: true, lang: true })
const { dataTheme } = useTheme()

const notFound = computed(() => props.error.statusCode === 404)

useHead(() => ({
  title: notFound.value ? t('errors.page.not_found_title') : t('errors.page.server_title'),
  htmlAttrs: { 'lang': head.value.htmlAttrs.lang, 'dir': head.value.htmlAttrs.dir, 'data-theme': dataTheme.value },
  // Error pages are never indexed (RELEASE_SCOPE §6.3); the 404 status itself comes from Nuxt.
  meta: [{ key: 'robots', name: 'robots', content: 'noindex, nofollow' }],
}))

function retry(): void {
  if (import.meta.client) window.location.reload()
}
</script>

<template>
  <NuxtLayout name="default">
    <div class="mx-auto flex max-w-xl flex-col items-center px-4 py-24 text-center">
      <UiEmptyState
        :icon="notFound ? SearchX : TriangleAlert"
        :title="notFound ? t('errors.page.not_found_title') : t('errors.page.server_title')"
        :description="notFound ? t('errors.page.not_found_body') : t('errors.page.server_body')"
      >
        <UiButton
          v-if="!notFound"
          @click="retry"
        >
          {{ t('common.actions.retry') }}
        </UiButton>
        <UiButton
          :variant="notFound ? 'primary' : 'secondary'"
          @click="clearError({ redirect: localePath('/') })"
        >
          {{ t('errors.page.back_home') }}
        </UiButton>
      </UiEmptyState>
    </div>
  </NuxtLayout>
</template>
