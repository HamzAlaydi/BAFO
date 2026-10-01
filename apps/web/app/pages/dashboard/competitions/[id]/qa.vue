<script setup lang="ts">
/**
 * W18 Q&A (SCREENS §2.4), by `viewer_role`: the issuer board (announcements, replies to every
 * thread, participant names with aliases) or the participant view (own questions and follow-ups).
 */
const ctx = useCompetitionContext()
</script>

<template>
  <CompetitionsIssuerQaBoard v-if="ctx.viewerRole.value === 'issuer'" />
  <CompetitionsParticipantQa v-else-if="ctx.viewerRole.value === 'participant'" />
</template>
