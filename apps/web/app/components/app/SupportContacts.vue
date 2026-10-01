<script setup lang="ts">
import { Mail, MessageCircle, Phone } from '@lucide/vue'

/** Support contacts from `AppConfig.support` (account gate, maintenance). Empty values are skipped. */
const { t } = useI18n()
const appConfig = useAppConfigStore()

const whatsappUrl = computed(() => {
  const digits = appConfig.support.whatsapp.replace(/\D/g, '')
  return digits ? `https://wa.me/${digits}` : null
})
</script>

<template>
  <ul
    v-if="appConfig.support.email || appConfig.support.phone || whatsappUrl"
    class="flex flex-col gap-2 text-sm"
    :aria-label="t('common.support.title')"
  >
    <li
      v-if="appConfig.support.email"
      class="flex items-center gap-2"
    >
      <Mail
        :size="16"
        class="shrink-0 text-fg-muted"
        aria-hidden="true"
      />
      <a
        :href="`mailto:${appConfig.support.email}`"
        class="link"
      ><bdi>{{ appConfig.support.email }}</bdi></a>
    </li>
    <li
      v-if="appConfig.support.phone"
      class="flex items-center gap-2"
    >
      <Phone
        :size="16"
        class="shrink-0 text-fg-muted"
        aria-hidden="true"
      />
      <a
        :href="`tel:${appConfig.support.phone}`"
        class="link"
      ><bdi>{{ appConfig.support.phone }}</bdi></a>
    </li>
    <li
      v-if="whatsappUrl"
      class="flex items-center gap-2"
    >
      <MessageCircle
        :size="16"
        class="shrink-0 text-fg-muted"
        aria-hidden="true"
      />
      <a
        :href="whatsappUrl"
        target="_blank"
        rel="noopener noreferrer"
        class="link"
      >{{ t('common.support.whatsapp') }}</a>
    </li>
  </ul>
</template>
