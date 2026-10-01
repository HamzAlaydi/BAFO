<script setup lang="ts">
/**
 * W21 My offers (participant) · `/dashboard/competitions/{id}/my-offers` (SCREENS §2.4). Only
 * participants reach it: the detail parent redirects other viewers to the overview.
 */
const ctx = useCompetitionContext()
const { t } = useI18n()
useSeoMeta({ title: () => t('competitions.detail.tabs.my_offers') })
</script>

<template>
  <CompetitionsParticipantMyOffers v-if="ctx.viewerRole.value === 'participant'" />
</template>
