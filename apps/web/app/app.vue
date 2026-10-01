<script setup lang="ts">
const head = useLocaleHead({ dir: true, lang: true, seo: true })
const { dataTheme } = useTheme()
const { t } = useI18n()
const appConfig = useAppConfigStore()

useHead(() => ({
  titleTemplate: (title?: string) => (title ? `${title} · ${t('common.app.name')}` : t('common.app.name')),
  htmlAttrs: {
    'lang': head.value.htmlAttrs.lang,
    'dir': head.value.htmlAttrs.dir,
    'data-theme': dataTheme.value,
  },
  link: head.value.link,
  meta: head.value.meta,
}))

useSeoMeta({
  description: () => t('common.app.description'),
  ogSiteName: () => t('common.app.name'),
})
</script>

<template>
  <div>
    <!--
      Skip link (SCREENS S9): fixed and moved above the viewport until focused. A fixed box never adds
      to the page's scrollable width, which an `sr-only` box at its static position did in RTL.
    -->
    <a
      href="#main"
      class="fixed start-4 top-4 z-[60] -translate-y-[calc(100%+2rem)] rounded-md bg-surface px-4 py-2 font-semibold text-fg shadow-md transition-transform focus:translate-y-0 focus-visible:outline-2 focus-visible:outline-ring motion-reduce:transition-none"
    >{{ t('common.app.skip_to_content') }}</a>
    <!-- S8 Maintenance: the whole app is replaced until the server stops reporting it. -->
    <AppMaintenanceState v-if="appConfig.inMaintenance" />
    <NuxtLayout v-else>
      <NuxtPage />
    </NuxtLayout>
    <UiToaster />
  </div>
</template>
