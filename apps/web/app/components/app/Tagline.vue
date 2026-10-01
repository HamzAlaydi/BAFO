<script setup lang="ts">
/**
 * "Best and Final Offer" / «أفضل عرض نهائي» with the guide's emphasis rule: the key words bold and
 * green, the rest regular and grey (05_brand.md §2.4). For marketing and sign-in only, not dense UI.
 */
defineProps<{ inverse?: boolean }>()
const { t } = useI18n()
</script>

<template>
  <i18n-t
    keypath="common.brand.tagline.template"
    tag="span"
    scope="global"
    :class="inverse ? 'text-gray-300' : 'text-fg-muted'"
  >
    <template #best>
      <strong
        class="font-extrabold"
        :class="inverse ? 'text-green-bright' : 'text-brand'"
      >{{ t('common.brand.tagline.best') }}</strong>
    </template>
    <template #offer>
      <strong
        class="font-extrabold"
        :class="inverse ? 'text-green-bright' : 'text-brand'"
      >{{ t('common.brand.tagline.offer') }}</strong>
    </template>
  </i18n-t>
</template>
