<script setup lang="ts">
/** `/login` (scaffold path) → `/auth/login`, keeping the query (SCREENS CD2). */
definePageMeta({
  middleware: [to => navigateTo(useLocalePath()({ path: '/auth/login', query: to.query }), { redirectCode: 301 })],
})
</script>

<template>
  <div />
</template>
