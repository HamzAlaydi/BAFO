<script setup lang="ts">
/** `/register` (scaffold path) → `/auth/register`, keeping the query (SCREENS CD2). */
definePageMeta({
  middleware: [to => navigateTo(useLocalePath()({ path: '/auth/register', query: to.query }), { redirectCode: 301 })],
})
</script>

<template>
  <div />
</template>
