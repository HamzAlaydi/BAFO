<script setup lang="ts">
/**
 * Upload and job progress. With `value` it is determinate (0–100); without it, indeterminate.
 * The bar fills from the inline start (right in Arabic).
 */
defineProps<{
  label: string
  value?: number | null
}>()
</script>

<template>
  <div
    role="progressbar"
    :aria-label="label"
    aria-valuemin="0"
    aria-valuemax="100"
    :aria-valuenow="value ?? undefined"
    class="relative h-2 w-full overflow-hidden rounded-full bg-neutral-soft"
  >
    <div
      v-if="value !== null && value !== undefined"
      class="h-full rounded-full bg-brand transition-[width] duration-300"
      :style="{ width: `${Math.min(100, Math.max(0, value))}%` }"
    />
    <div
      v-else
      class="absolute inset-y-0 start-0 w-1/3 animate-pulse rounded-full bg-brand"
    />
  </div>
</template>
