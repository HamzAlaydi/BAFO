<script setup lang="ts">
/**
 * Sanitised Markdown (legal documents). Raw HTML in the source is escaped and unsafe link schemes
 * are rejected by the renderer configuration (`utils/markdown.ts`), so the output is safe to bind.
 * Pass the page's `title` and a `headingOffset` when the page renders its own `<h1>`.
 */
const props = defineProps<{ source: string, title?: string, headingOffset?: number }>()
const html = computed(() => renderMarkdown(props.source, { title: props.title, headingOffset: props.headingOffset }))
</script>

<template>
  <!-- eslint-disable vue/no-v-html -- sanitised by renderMarkdown (html: false, link allow-list) -->
  <div
    class="markdown"
    v-html="html"
  />
  <!-- eslint-enable vue/no-v-html -->
</template>
