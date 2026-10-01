<script setup lang="ts">
/**
 * Bot trap `website_url` (API.md §0.8, SCREENS S7): visually hidden, out of the tab order,
 * `autocomplete="off"` and hidden from assistive technology. People never fill it; a non-empty
 * value makes the API reject the request.
 */
const model = defineModel<string>({ default: '' })
const id = useId()
// No label: the field is hidden from assistive technology and never meant for people.
</script>

<template>
  <div
    class="pointer-events-none absolute -z-10 size-px overflow-hidden opacity-0"
    aria-hidden="true"
  >
    <input
      :id="id"
      v-model="model"
      type="text"
      name="website_url"
      tabindex="-1"
      autocomplete="off"
    >
  </div>
</template>
