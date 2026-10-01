<script setup lang="ts">
/** Page title (the page's only `h1`), optional description and actions. Actions wrap below on phones. */
defineProps<{
  title: string
  description?: string
}>()
</script>

<template>
  <header class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
    <div class="min-w-0">
      <slot name="eyebrow" />
      <h1 class="text-2xl font-bold text-balance text-fg">
        {{ title }}
      </h1>
      <p
        v-if="description"
        class="mt-1 max-w-2xl text-fg-muted"
      >
        {{ description }}
      </p>
      <slot name="meta" />
    </div>
    <div
      v-if="$slots.actions"
      class="flex shrink-0 flex-wrap items-center gap-2"
    >
      <slot name="actions" />
    </div>
  </header>
</template>
