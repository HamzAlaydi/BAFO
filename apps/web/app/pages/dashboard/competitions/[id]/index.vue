<script setup lang="ts">
/**
 * W14 Overview (SCREENS §2.4), by `viewer_role`: the issuer overview (timeline, rules, counts, fees,
 * documents, setup checklist for drafts), the participant overview, or the invitee's invitation view.
 * Each view reads the competition from the detail parent (`useCompetitionContext()`).
 */
const ctx = useCompetitionContext()
</script>

<template>
  <CompetitionsIssuerOverview v-if="ctx.viewerRole.value === 'issuer'" />
  <CompetitionsParticipantOverview v-else-if="ctx.viewerRole.value === 'participant'" />
  <CompetitionsParticipantInviteeView v-else-if="ctx.viewerRole.value === 'invitee'" />
</template>
