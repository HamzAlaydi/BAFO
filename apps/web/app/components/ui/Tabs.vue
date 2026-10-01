<script setup lang="ts">
import type { TabItem } from '~/types/ui'

/**
 * WAI-ARIA tabs with automatic activation. Arrow keys follow the reading direction
 * (in RTL, ArrowLeft moves to the next tab). Panels: a slot named after each tab key,
 * or the default slot with `{ active }`.
 */
const props = defineProps<{
  items: TabItem[]
  /** Accessible name for the tab list. */
  label: string
}>()

const model = defineModel<string>({ required: true })
const locale = useAppLocale()
const baseId = useId()
const tabRefs = ref<HTMLButtonElement[]>([])

const enabled = computed(() => props.items.filter(item => !item.disabled))

function tabId(key: string): string {
  return `${baseId}-tab-${key}`
}

function panelId(key: string): string {
  return `${baseId}-panel-${key}`
}

function select(key: string): void {
  model.value = key
  nextTick(() => {
    tabRefs.value.find(el => el.dataset.key === key)?.focus()
  })
}

function onKeydown(event: KeyboardEvent): void {
  const keys = enabled.value.map(item => item.key)
  const index = keys.indexOf(model.value)
  if (index === -1 || keys.length === 0) return
  const forward = locale.value === 'ar' ? 'ArrowLeft' : 'ArrowRight'
  const backward = locale.value === 'ar' ? 'ArrowRight' : 'ArrowLeft'
  let next: string | undefined
  if (event.key === forward) next = keys[(index + 1) % keys.length]
  else if (event.key === backward) next = keys[(index - 1 + keys.length) % keys.length]
  else if (event.key === 'Home') next = keys[0]
  else if (event.key === 'End') next = keys[keys.length - 1]
  if (next !== undefined) {
    event.preventDefault()
    select(next)
  }
}
</script>

<template>
  <div class="flex flex-col gap-4">
    <div
      role="tablist"
      :aria-label="label"
      class="-mb-px flex gap-1 overflow-x-auto border-b border-line"
      @keydown="onKeydown"
    >
      <button
        v-for="item in items"
        :id="tabId(item.key)"
        :key="item.key"
        ref="tabRefs"
        type="button"
        role="tab"
        :data-key="item.key"
        :aria-selected="model === item.key"
        :aria-controls="panelId(item.key)"
        :tabindex="model === item.key ? 0 : -1"
        :disabled="item.disabled"
        class="relative inline-flex h-11 shrink-0 items-center gap-2 border-b-2 px-3 text-[0.9375rem] font-semibold whitespace-nowrap transition-colors focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ring disabled:cursor-not-allowed disabled:opacity-50"
        :class="model === item.key ? 'border-brand text-fg' : 'border-transparent text-fg-muted hover:text-fg'"
        @click="select(item.key)"
      >
        <component
          :is="item.icon"
          v-if="item.icon"
          :size="16"
          aria-hidden="true"
        />
        {{ item.label }}
        <span
          v-if="item.count !== undefined"
          class="rounded-full bg-neutral-soft px-2 text-xs text-neutral-soft-fg tabular-nums"
        >{{ item.count }}</span>
      </button>
    </div>
    <div
      v-for="item in items"
      v-show="model === item.key"
      :id="panelId(item.key)"
      :key="item.key"
      role="tabpanel"
      :aria-labelledby="tabId(item.key)"
      tabindex="0"
      class="focus-visible:outline-2 focus-visible:outline-ring"
    >
      <slot
        v-if="model === item.key"
        :name="item.key"
      >
        <slot :active="item.key" />
      </slot>
    </div>
  </div>
</template>
