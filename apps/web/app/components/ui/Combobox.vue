<script setup lang="ts" generic="T extends string">
import { Check, ChevronDown, X } from '@lucide/vue'
import type { SelectOption } from '~/types/ui'

/**
 * Multi-select combobox (WAI-ARIA 1.2 combobox + multiselectable listbox): type to filter, arrow keys
 * move, Enter toggles, Escape closes. Selected values show as removable chips. `max` caps the
 * selection (e.g. 20 categories); options beyond it are disabled.
 */
defineOptions({ inheritAttrs: false })

const { rootAttrs, controlAttrs } = useFieldAttrs()

const props = withDefaults(defineProps<{
  options: SelectOption<T>[]
  label: string
  hint?: string
  error?: string | null
  id?: string
  placeholder?: string
  required?: boolean
  disabled?: boolean
  max?: number
  loading?: boolean
}>(), {
  max: Number.POSITIVE_INFINITY,
})

const model = defineModel<T[]>({ default: () => [] })
const { t } = useI18n()
const autoId = useId()
const inputId = computed(() => props.id ?? `combobox-${autoId}`)
const listboxId = computed(() => `${inputId.value}-listbox`)
const root = useTemplateRef<HTMLElement>('root')
const query = ref('')
const open = ref(false)
const activeIndex = ref(-1)

const selected = computed(() => new Set(model.value))
const selectedOptions = computed(() => props.options.filter(option => selected.value.has(option.value)))
const atMax = computed(() => model.value.length >= props.max)

const filtered = computed(() => {
  const needle = normalizeDigits(query.value).trim().toLocaleLowerCase()
  if (!needle) return props.options
  return props.options.filter(option => option.label.toLocaleLowerCase().includes(needle))
})

function optionId(index: number): string {
  return `${listboxId.value}-${index}`
}

function isDisabled(option: SelectOption<T>): boolean {
  return Boolean(option.disabled) || (atMax.value && !selected.value.has(option.value))
}

function toggle(option: SelectOption<T>): void {
  if (props.disabled || isDisabled(option)) return
  model.value = selected.value.has(option.value)
    ? model.value.filter(value => value !== option.value)
    : [...model.value, option.value]
}

function remove(value: T): void {
  model.value = model.value.filter(item => item !== value)
}

function show(): void {
  if (props.disabled) return
  open.value = true
}

function move(delta: number): void {
  show()
  const count = filtered.value.length
  if (count === 0) return
  activeIndex.value = (activeIndex.value + delta + count) % count
  nextTick(() => document.getElementById(optionId(activeIndex.value))?.scrollIntoView({ block: 'nearest' }))
}

function onKeydown(event: KeyboardEvent): void {
  if (event.key === 'ArrowDown') {
    event.preventDefault()
    move(1)
  }
  else if (event.key === 'ArrowUp') {
    event.preventDefault()
    move(-1)
  }
  else if (event.key === 'Enter') {
    const option = filtered.value[activeIndex.value]
    if (open.value && option) {
      event.preventDefault()
      toggle(option)
    }
  }
  else if (event.key === 'Escape') {
    if (open.value) {
      event.preventDefault()
      open.value = false
    }
  }
  else if (event.key === 'Backspace' && query.value === '' && model.value.length > 0) {
    model.value = model.value.slice(0, -1)
  }
}

watch(query, () => {
  activeIndex.value = filtered.value.length > 0 ? 0 : -1
  show()
})

onClickOutside(root, () => {
  open.value = false
})
</script>

<template>
  <UiField
    v-bind="rootAttrs()"
    :id="inputId"
    v-slot="{ describedby, invalid }"
    :label="label"
    :hint="hint"
    :error="error"
    :required="required"
  >
    <div
      ref="root"
      class="relative"
    >
      <div
        class="flex min-h-11 flex-wrap items-center gap-1.5 rounded-md border bg-surface px-2 py-1.5 transition-colors focus-within:outline-2 focus-within:outline-offset-0 focus-within:outline-ring"
        :class="[invalid ? 'border-danger' : 'border-line-strong', disabled && 'cursor-not-allowed bg-surface-muted opacity-70']"
      >
        <ul
          v-if="selectedOptions.length > 0"
          class="contents"
          :aria-label="t('common.combobox.selected', { count: selectedOptions.length })"
        >
          <li
            v-for="option in selectedOptions"
            :key="option.value"
            class="inline-flex max-w-full items-center gap-1 rounded-full bg-primary-soft py-0.5 ps-2.5 pe-1 text-sm font-semibold text-primary-soft-fg"
          >
            <span class="truncate">{{ option.label }}</span>
            <button
              type="button"
              class="inline-flex size-6 shrink-0 items-center justify-center rounded-full hover:bg-primary/10 focus-visible:outline-2 focus-visible:outline-ring"
              :aria-label="t('common.combobox.remove', { label: option.label })"
              :disabled="disabled"
              @click="remove(option.value)"
            >
              <X
                :size="14"
                aria-hidden="true"
              />
            </button>
          </li>
        </ul>
        <input
          :id="inputId"
          v-model="query"
          v-bind="controlAttrs()"
          type="text"
          role="combobox"
          aria-autocomplete="list"
          :aria-expanded="open"
          :aria-controls="listboxId"
          :aria-activedescendant="open && activeIndex >= 0 ? optionId(activeIndex) : undefined"
          :aria-invalid="invalid || undefined"
          :aria-describedby="describedby"
          :placeholder="placeholder"
          :disabled="disabled"
          autocomplete="off"
          class="h-8 min-w-[8rem] flex-1 bg-transparent px-1 text-fg outline-none placeholder:text-fg-muted focus-visible:outline-none disabled:cursor-not-allowed"
          @focus="show"
          @click="show"
          @keydown="onKeydown"
        >
        <ChevronDown
          :size="18"
          class="shrink-0 text-fg-muted"
          aria-hidden="true"
        />
      </div>
      <ul
        v-show="open"
        :id="listboxId"
        role="listbox"
        aria-multiselectable="true"
        :aria-label="label"
        class="absolute inset-x-0 top-full z-40 mt-1 max-h-64 overflow-y-auto rounded-lg border border-line bg-surface py-1 shadow-lg"
      >
        <li
          v-if="loading"
          class="px-3.5 py-2 text-sm text-fg-muted"
          role="presentation"
        >
          {{ t('common.loading') }}
        </li>
        <li
          v-else-if="filtered.length === 0"
          class="px-3.5 py-2 text-sm text-fg-muted"
          role="presentation"
        >
          {{ t('common.combobox.no_results') }}
        </li>
        <li
          v-for="(option, index) in filtered"
          :id="optionId(index)"
          :key="option.value"
          role="option"
          :aria-selected="selected.has(option.value)"
          :aria-disabled="isDisabled(option) || undefined"
          class="flex cursor-pointer items-center gap-3 px-3.5 py-2 text-sm"
          :class="[
            index === activeIndex ? 'bg-surface-muted' : '',
            isDisabled(option) ? 'cursor-not-allowed opacity-50' : 'hover:bg-surface-muted',
          ]"
          @mousedown.prevent
          @click="toggle(option)"
          @mousemove="activeIndex = index"
        >
          <span
            class="flex size-4 shrink-0 items-center justify-center rounded-xs border"
            :class="selected.has(option.value) ? 'border-primary bg-primary text-primary-fg' : 'border-line-strong'"
            aria-hidden="true"
          >
            <Check
              v-if="selected.has(option.value)"
              :size="12"
            />
          </span>
          <span class="min-w-0 flex-1">{{ option.label }}</span>
        </li>
      </ul>
    </div>
    <p
      v-if="Number.isFinite(max)"
      class="text-xs text-fg-muted tabular-nums"
    >
      {{ t('common.combobox.count', { count: model.length, max }) }}
    </p>
  </UiField>
</template>
