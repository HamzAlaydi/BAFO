<script setup lang="ts">
/**
 * The BAFO mark drawn as a faint outline for section backgrounds: the two chevrons of
 * `AppBrandMark`, stroked in the current colour at low opacity. Decorative only, never mirrored.
 */
withDefaults(defineProps<{ class?: string }>(), { class: '' })
</script>

<template>
  <svg
    viewBox="0 0 200 200"
    :class="$props.class"
    class="pointer-events-none select-none"
    aria-hidden="true"
    focusable="false"
    fill="none"
    stroke="currentColor"
    stroke-linecap="round"
    stroke-linejoin="round"
  >
    <polyline
      points="55,160 100,100 145,160"
      stroke-width="10"
    />
    <polyline
      points="45,45 100,135 155,45"
      stroke-width="14"
    />
  </svg>
</template>
