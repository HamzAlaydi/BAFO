<script setup lang="ts">
withDefaults(defineProps<{
  as?: 'div' | 'section' | 'article' | 'li'
  title?: string
  description?: string
  padding?: 'none' | 'sm' | 'md' | 'lg'
}>(), {
  as: 'div',
  padding: 'md',
})

const paddingClasses = { none: '', sm: 'p-4', md: 'p-5 sm:p-6', lg: 'p-6 sm:p-8' } as const
</script>

<template>
  <component
    :is="as"
    class="flex flex-col rounded-lg border border-line bg-surface shadow-xs"
  >
    <header
      v-if="title || $slots.header || $slots.actions"
      class="flex flex-wrap items-start justify-between gap-3 border-b border-line px-5 py-4 sm:px-6"
    >
      <slot name="header">
        <div class="min-w-0">
          <h3 class="text-base font-bold text-fg">
            {{ title }}
          </h3>
          <p
            v-if="description"
            class="mt-0.5 text-sm text-fg-muted"
          >
            {{ description }}
          </p>
        </div>
      </slot>
      <div
        v-if="$slots.actions"
        class="flex shrink-0 items-center gap-2"
      >
        <slot name="actions" />
      </div>
    </header>
    <div
      class="flex-1"
      :class="paddingClasses[padding]"
    >
      <slot />
    </div>
    <footer
      v-if="$slots.footer"
      class="flex flex-wrap items-center justify-end gap-2 border-t border-line px-5 py-3 sm:px-6"
    >
      <slot name="footer" />
    </footer>
  </component>
</template>
