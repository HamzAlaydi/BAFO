<script setup lang="ts">
/** Loading placeholder; size it with classes (`h-4 w-32`). `lines` renders a text block. */
withDefaults(defineProps<{
  shape?: 'rect' | 'circle'
  lines?: number
}>(), {
  shape: 'rect',
  lines: 0,
})
</script>

<template>
  <div
    v-if="lines > 0"
    class="flex flex-col gap-2"
    aria-hidden="true"
  >
    <span
      v-for="n in lines"
      :key="n"
      class="block h-3.5 animate-pulse rounded-sm bg-surface-muted"
      :class="n === lines && lines > 1 ? 'w-2/3' : 'w-full'"
    />
  </div>
  <span
    v-else
    class="block animate-pulse bg-surface-muted"
    :class="shape === 'circle' ? 'rounded-full' : 'rounded-sm'"
    aria-hidden="true"
  />
</template>
