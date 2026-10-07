<script setup lang="ts">
import { ChevronDown } from '@lucide/vue'

export interface LandingFaqItem {
  key: string
  question: string
  answer: string
}

/**
 * FAQ accordion (RELEASE_SCOPE §6.1 row 7): native `<details>`/`<summary>` so every item is
 * keyboard-operable and readable without JavaScript. One item open at a time: the exclusive
 * `name` attribute where supported, and a toggle handler everywhere else.
 */
defineProps<{ items: LandingFaqItem[] }>()

const groupName = `landing-faq-${useId()}`
const details = ref<HTMLDetailsElement[]>([])

function onToggle(event: Event): void {
  const opened = event.target as HTMLDetailsElement
  if (!opened.open) return
  for (const element of details.value) {
    if (element !== opened && element.open) element.open = false
  }
}
</script>

<template>
  <div class="divide-y divide-line border-y border-line">
    <details
      v-for="item in items"
      :key="item.key"
      ref="details"
      :name="groupName"
      class="group"
      @toggle="onToggle"
    >
      <summary class="flex min-h-14 cursor-pointer list-none items-center justify-between gap-4 py-4 text-start focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring [&::-webkit-details-marker]:hidden">
        <h3 class="text-base font-semibold text-fg sm:text-lg">
          {{ item.question }}
        </h3>
        <ChevronDown
          :size="20"
          class="shrink-0 text-fg-muted transition-transform group-open:rotate-180 motion-reduce:transition-none"
          aria-hidden="true"
        />
      </summary>
      <p class="max-w-prose pb-5 pe-10 text-fg-muted">
        {{ item.answer }}
      </p>
    </details>
  </div>
</template>
