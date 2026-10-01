<script setup lang="ts">
/**
 * The BAFO mark (icon_master.svg): red chevron up, heavier green chevron down on top.
 * Never mirrored, recoloured or flipped (05_brand §2.5). Colours come from the brand tokens.
 */
withDefaults(defineProps<{
  /** Tailwind size classes, e.g. `size-4`. */
  sizeClass?: string
  /** Accessible name; decorative when omitted. */
  label?: string
}>(), {
  sizeClass: 'size-6',
})
</script>

<template>
  <svg
    viewBox="0 0 200 200"
    :class="sizeClass"
    class="shrink-0"
    :role="label ? 'img' : undefined"
    :aria-label="label"
    :aria-hidden="label ? undefined : 'true'"
    focusable="false"
  >
    <polyline
      points="55,160 100,100 145,160"
      fill="none"
      class="stroke-red-500"
      stroke-width="22"
      stroke-linecap="round"
      stroke-linejoin="round"
    />
    <polyline
      points="45,45 100,135 155,45"
      fill="none"
      class="stroke-green-500"
      stroke-width="32"
      stroke-linecap="round"
      stroke-linejoin="round"
    />
  </svg>
</template>
