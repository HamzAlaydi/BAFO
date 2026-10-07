<script setup lang="ts">
/**
 * BAFO lockup: the colour mark (vector, from icon_master.svg) plus the supplied wordmark artwork.
 * The wordmark is never re-typeset in live text (05_brand.md §2.4). The lockup order follows the
 * reading direction (mark on the right in Arabic); the mark itself is never mirrored.
 */
const props = withDefaults(defineProps<{
  variant?: 'lockup' | 'mark'
  size?: 'sm' | 'md' | 'lg'
  /** On charcoal/dark surfaces regardless of theme (white wordmark). */
  inverse?: boolean
}>(), {
  variant: 'lockup',
  size: 'md',
})

const { t } = useI18n()
const locale = useAppLocale()

const markSize = { sm: 'size-7', md: 'size-8', lg: 'size-12' } as const
const wordmarkHeight = computed(() => {
  const heights = locale.value === 'ar'
    ? { sm: 'h-7', md: 'h-8', lg: 'h-12' }
    : { sm: 'h-4', md: 'h-[1.125rem]', lg: 'h-7' }
  return heights[props.size]
})

const wordmark = computed(() => ({
  light: `/brand/wordmark-${locale.value}-charcoal.png`,
  dark: `/brand/wordmark-${locale.value}-white.png`,
}))

// Intrinsic pixel size of the artwork: gives the browser the aspect ratio before the file arrives,
// so the header does not shift when the wordmark loads (the CSS height still sets the rendered size).
const wordmarkSize = computed(() => (locale.value === 'ar' ? { width: 381, height: 295 } : { width: 650, height: 195 }))
</script>

<template>
  <span
    class="inline-flex items-center gap-2.5"
    role="img"
    :aria-label="t('common.app.name')"
  >
    <AppBrandMark :size-class="markSize[size]" />
    <template v-if="variant === 'lockup'">
      <img
        v-if="inverse"
        :src="wordmark.dark"
        alt=""
        :width="wordmarkSize.width"
        :height="wordmarkSize.height"
        :class="wordmarkHeight"
        class="w-auto"
      >
      <template v-else>
        <img
          :src="wordmark.light"
          alt=""
          :width="wordmarkSize.width"
          :height="wordmarkSize.height"
          :class="wordmarkHeight"
          class="w-auto dark:hidden"
        >
        <img
          :src="wordmark.dark"
          alt=""
          :width="wordmarkSize.width"
          :height="wordmarkSize.height"
          :class="wordmarkHeight"
          class="hidden w-auto dark:block"
        >
      </template>
    </template>
  </span>
</template>
