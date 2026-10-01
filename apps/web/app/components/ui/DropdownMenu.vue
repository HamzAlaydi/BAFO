<script setup lang="ts">
import { Check } from '@lucide/vue'
import { NuxtLinkLocale } from '#components'
import type { MenuAction, MenuEntry } from '~/types/ui'

/**
 * Menu button. With `items` it is an ARIA menu (arrow keys, Home/End, Esc returns focus).
 * Without `items`, the `panel` slot renders free content (e.g. notifications) in a popover.
 * `align` is logical: 'end' lines the panel up with the trigger's end edge (left in Arabic).
 */
const props = withDefaults(defineProps<{
  /** Accessible name of the trigger. */
  label: string
  items?: MenuEntry[]
  align?: 'start' | 'end'
  width?: 'auto' | 'sm' | 'md' | 'lg'
  triggerClass?: string
}>(), {
  align: 'end',
  width: 'sm',
})

const emit = defineEmits<{ 'select': [key: string], 'update:open': [open: boolean] }>()
const open = ref(false)
const root = useTemplateRef<HTMLElement>('root')
const trigger = useTemplateRef<HTMLButtonElement>('trigger')
const panel = useTemplateRef<HTMLElement>('panel')
const menuId = useId()
const isMenu = computed(() => props.items !== undefined)

onClickOutside(root, () => {
  open.value = false
})

watch(open, value => emit('update:open', value))

function menuItems(): HTMLElement[] {
  return Array.from(panel.value?.querySelectorAll<HTMLElement>('[role^="menuitem"]:not([aria-disabled="true"])') ?? [])
}

function focusItem(index: number): void {
  const list = menuItems()
  if (list.length === 0) return
  list[(index + list.length) % list.length]?.focus()
}

async function show(focus: 'first' | 'last' | 'none' = 'first'): Promise<void> {
  open.value = true
  await nextTick()
  if (!isMenu.value || focus === 'none') return
  focusItem(focus === 'first' ? 0 : -1)
}

function close(returnFocus = true): void {
  open.value = false
  if (returnFocus) trigger.value?.focus()
}

function toggle(): void {
  if (open.value) close(false)
  else void show(isMenu.value ? 'first' : 'none')
}

function onTriggerKeydown(event: KeyboardEvent): void {
  if (!isMenu.value) return
  if (event.key === 'ArrowDown') {
    event.preventDefault()
    void show('first')
  }
  else if (event.key === 'ArrowUp') {
    event.preventDefault()
    void show('last')
  }
}

function onPanelKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape') {
    event.preventDefault()
    close()
    return
  }
  if (event.key === 'Tab') {
    open.value = false
    return
  }
  if (!isMenu.value) return
  const list = menuItems()
  const index = list.indexOf(document.activeElement as HTMLElement)
  const moves: Record<string, number> = { ArrowDown: index + 1, ArrowUp: index - 1, Home: 0, End: list.length - 1 }
  const next = moves[event.key]
  if (next !== undefined) {
    event.preventDefault()
    focusItem(next)
  }
}

function onSelect(item: MenuAction): void {
  if (item.disabled) return
  emit('select', item.key)
  close(!item.to)
}

const widthClasses = { auto: 'w-max', sm: 'w-56', md: 'w-72', lg: 'w-80 max-w-[calc(100vw-2rem)]' } as const
</script>

<template>
  <div
    ref="root"
    class="relative inline-flex"
  >
    <button
      ref="trigger"
      type="button"
      :aria-label="$slots.trigger ? undefined : label"
      :aria-haspopup="isMenu ? 'menu' : 'dialog'"
      :aria-expanded="open"
      :aria-controls="open ? menuId : undefined"
      class="inline-flex items-center rounded-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
      :class="triggerClass"
      @click="toggle"
      @keydown="onTriggerKeydown"
    >
      <slot
        name="trigger"
        :open="open"
      >
        <span class="sr-only">{{ label }}</span>
      </slot>
    </button>
    <Transition
      enter-from-class="opacity-0 -translate-y-1"
      enter-active-class="transition duration-150 ease-out"
      leave-to-class="opacity-0"
      leave-active-class="transition duration-100 ease-in"
    >
      <div
        v-if="open"
        :id="menuId"
        ref="panel"
        :role="isMenu ? 'menu' : 'dialog'"
        :aria-label="label"
        class="absolute top-full z-40 mt-2 overflow-hidden rounded-lg border border-line bg-surface text-fg shadow-lg"
        :class="[align === 'end' ? 'end-0' : 'start-0', widthClasses[width]]"
        @keydown="onPanelKeydown"
      >
        <slot
          name="header"
          :close="close"
        />
        <ul
          v-if="items"
          class="py-1.5"
          role="none"
        >
          <template
            v-for="entry in items"
            :key="entry.key"
          >
            <li
              v-if="entry.type === 'separator'"
              role="separator"
              class="my-1.5 border-t border-line"
            />
            <li
              v-else
              role="none"
            >
              <component
                :is="entry.to && !entry.disabled ? NuxtLinkLocale : 'button'"
                :to="entry.to && !entry.disabled ? entry.to : undefined"
                :type="entry.to ? undefined : 'button'"
                :role="entry.checked === undefined ? 'menuitem' : 'menuitemradio'"
                :aria-checked="entry.checked"
                tabindex="-1"
                :aria-disabled="entry.disabled || undefined"
                class="flex w-full items-center gap-3 px-3.5 py-2 text-start text-sm font-medium transition-colors focus:outline-none"
                :class="[
                  entry.danger ? 'text-danger hover:bg-danger-soft focus:bg-danger-soft' : 'text-fg hover:bg-surface-muted focus:bg-surface-muted',
                  entry.disabled && 'cursor-not-allowed opacity-50',
                ]"
                @click="onSelect(entry)"
              >
                <component
                  :is="entry.icon"
                  v-if="entry.icon"
                  :size="16"
                  class="shrink-0"
                  :class="entry.danger ? '' : 'text-fg-muted'"
                  aria-hidden="true"
                />
                <span class="min-w-0 flex-1 truncate">{{ entry.label }}</span>
                <Check
                  v-if="entry.checked"
                  :size="16"
                  class="shrink-0 text-brand"
                  aria-hidden="true"
                />
              </component>
            </li>
          </template>
        </ul>
        <slot
          name="panel"
          :close="close"
        />
      </div>
    </Transition>
  </div>
</template>
