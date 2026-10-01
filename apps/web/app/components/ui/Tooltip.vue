<script setup lang="ts">
/**
 * Short supplementary text on hover/focus. Bind the slot's `describedby` to the trigger:
 *   <UiTooltip :text="t('...')" v-slot="{ describedby }"><button :aria-describedby="describedby">…</button></UiTooltip>
 * Never put essential information only in a tooltip.
 */
withDefaults(defineProps<{
  text: string
  side?: 'top' | 'bottom'
}>(), {
  side: 'top',
})

const id = useId()
const visible = ref(false)
</script>

<template>
  <span
    class="relative inline-flex"
    @mouseenter="visible = true"
    @mouseleave="visible = false"
    @focusin="visible = true"
    @focusout="visible = false"
    @keydown.esc="visible = false"
  >
    <slot :describedby="id" />
    <!-- Full-width flex row: justify-center overflows symmetrically, so the bubble stays centred in both directions. -->
    <span
      class="pointer-events-none absolute inset-x-0 z-50 flex justify-center"
      :class="side === 'top' ? 'bottom-full mb-2' : 'top-full mt-2'"
    >
      <span
        :id="id"
        role="tooltip"
        class="w-max max-w-64 rounded-sm bg-fg px-2.5 py-1.5 text-center text-xs font-medium text-fg-inverse shadow-md transition-opacity duration-100"
        :class="visible ? 'opacity-100' : 'opacity-0'"
      >
        {{ text }}
      </span>
    </span>
  </span>
</template>
