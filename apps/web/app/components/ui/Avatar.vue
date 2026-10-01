<script setup lang="ts">
const props = withDefaults(defineProps<{
  name: string
  src?: string | null
  size?: 'xs' | 'sm' | 'md' | 'lg'
}>(), {
  size: 'md',
})

const failed = ref(false)
watch(() => props.src, () => {
  failed.value = false
})

const sizeClasses = { xs: 'size-6 text-[0.625rem]', sm: 'size-8 text-xs', md: 'size-10 text-sm', lg: 'size-14 text-lg' } as const
const palettes = [
  'bg-primary-soft text-primary-soft-fg',
  'bg-info-soft text-info-soft-fg',
  'bg-warning-soft text-warning-soft-fg',
  'bg-neutral-soft text-neutral-soft-fg',
] as const
const palette = computed(() => palettes[hashToBucket(props.name, palettes.length)])
</script>

<template>
  <span
    class="relative inline-flex shrink-0 items-center justify-center overflow-hidden rounded-full font-bold select-none"
    :class="[sizeClasses[size], !(src && !failed) && palette]"
    role="img"
    :aria-label="name"
  >
    <img
      v-if="src && !failed"
      :src="src"
      alt=""
      class="size-full object-cover"
      @error="failed = true"
    >
    <span
      v-else
      aria-hidden="true"
    >{{ initials(name) }}</span>
  </span>
</template>
