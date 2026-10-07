<script setup lang="ts">
/** One `h2` per landing section, with an optional one-line subtitle; `id` is the section's `aria-labelledby` target. */
defineProps<{
  id: string
  title: string
  subtitle?: string
}>()
</script>

<template>
  <div class="max-w-2xl">
    <h2
      :id="id"
      class="text-3xl leading-tight font-bold text-balance text-fg sm:text-4xl"
    >
      {{ title }}
    </h2>
    <p
      v-if="subtitle"
      class="mt-3 text-lg text-fg-muted"
    >
      {{ subtitle }}
    </p>
  </div>
</template>
