<script setup lang="ts">
/**
 * W19 Live (SCREENS §2.4), by `viewer_role`: the issuer console (`IssuerLiveSnapshot`) or the
 * participant live room (`ParticipantLiveSnapshot`). Both read the parent's single realtime
 * subscription and `v`-ordered snapshot. Invitees have no live view: the parent redirects them.
 */
const ctx = useCompetitionContext()
</script>

<template>
  <CompetitionsIssuerLiveConsole v-if="ctx.viewerRole.value === 'issuer'" />
  <LiveParticipantRoom v-else-if="ctx.viewerRole.value === 'participant'" />
</template>
