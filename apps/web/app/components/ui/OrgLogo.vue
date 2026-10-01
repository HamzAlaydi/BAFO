<script setup lang="ts">
import { Building } from '@lucide/vue'

/** Organisation logo in a rounded square, falling back to initials. */
const props = withDefaults(defineProps<{
  name?: string | null
  src?: string | null
  size?: 'sm' | 'md' | 'lg'
}>(), {
  size: 'md',
})

const failed = ref(false)
watch(() => props.src, () => {
  failed.value = false
})

const sizeClasses = { sm: 'size-8 text-xs', md: 'size-10 text-sm', lg: 'size-16 text-xl' } as const
const iconSizes = { sm: 16, md: 20, lg: 28 } as const
const letters = computed(() => initials(props.name))
</script>

<template>
  <span
    class="inline-flex shrink-0 items-center justify-center overflow-hidden rounded-md border border-line bg-surface-muted font-bold text-fg-muted select-none"
    :class="sizeClasses[size]"
    role="img"
    :aria-label="name ?? undefined"
  >
    <img
      v-if="src && !failed"
      :src="src"
      alt=""
      class="size-full object-contain p-1"
      @error="failed = true"
    >
    <span
      v-else-if="letters"
      aria-hidden="true"
    >{{ letters }}</span>
    <Building
      v-else
      :size="iconSizes[size]"
      aria-hidden="true"
    />
  </span>
</template>
