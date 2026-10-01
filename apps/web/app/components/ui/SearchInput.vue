<script setup lang="ts">
import { Search, X } from '@lucide/vue'

/**
 * Search box (CONVENTIONS §9.3): the model updates 300 ms after the user stops typing; the clear
 * button resets it at once. The label is visually hidden but always present.
 */
const props = withDefaults(defineProps<{
  label: string
  placeholder?: string
  debounceMs?: number
  id?: string
}>(), {
  debounceMs: 300,
})

const model = defineModel<string>({ default: '' })
const { t } = useI18n()
const autoId = useId()
const inputId = computed(() => props.id ?? `search-${autoId}`)
const text = ref(model.value)

watch(model, (value) => {
  if (value !== text.value) text.value = value
})

const commit = useDebounceFn((value: string) => {
  model.value = value
}, () => props.debounceMs)

function onInput(event: Event): void {
  text.value = (event.target as HTMLInputElement).value
  void commit(text.value)
}

function clear(): void {
  text.value = ''
  model.value = ''
}
</script>

<template>
  <div class="relative flex h-11 items-center gap-2 rounded-md border border-line-strong bg-surface px-3 focus-within:outline-2 focus-within:outline-offset-0 focus-within:outline-ring">
    <label
      :for="inputId"
      class="sr-only"
    >{{ label }}</label>
    <Search
      :size="18"
      class="shrink-0 text-fg-muted"
      aria-hidden="true"
    />
    <input
      :id="inputId"
      :value="text"
      type="search"
      :placeholder="placeholder"
      autocomplete="off"
      class="h-full min-w-0 flex-1 bg-transparent text-fg outline-none placeholder:text-fg-muted focus-visible:outline-none [&::-webkit-search-cancel-button]:hidden"
      @input="onInput"
      @keydown.esc="clear"
    >
    <UiIconButton
      v-if="text"
      :icon="X"
      size="sm"
      :label="t('common.actions.clear_search')"
      class="-me-2"
      @click="clear"
    />
  </div>
</template>
