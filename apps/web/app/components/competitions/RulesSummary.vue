<script setup lang="ts">
import { Check } from '@lucide/vue'

/**
 * The server's `rules_summary` lines (localised by `Accept-Language`, ARCHITECTURE §7.16).
 * The client never rebuilds them from the rules object.
 */
defineProps<{
  lines: string[]
  title?: string
}>()
</script>

<template>
  <section
    v-if="lines.length > 0"
    class="flex flex-col gap-3"
  >
    <h2
      v-if="title"
      class="text-base font-bold text-fg"
    >
      {{ title }}
    </h2>
    <ul class="flex flex-col gap-2 text-sm text-fg">
      <li
        v-for="(line, index) in lines"
        :key="index"
        class="flex items-start gap-2"
      >
        <Check
          :size="16"
          class="mt-0.5 shrink-0 text-brand"
          aria-hidden="true"
        />
        <span>{{ line }}</span>
      </li>
    </ul>
  </section>
</template>
