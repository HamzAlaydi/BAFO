<script setup lang="ts">
/**
 * W22 Evaluation and award (issuer) · `/dashboard/competitions/{id}/award` (SCREENS §2.4): final
 * standings, award with justification, BAFO round, close without award, revoke, result report.
 */
const { t } = useI18n()
useSeoMeta({ title: () => t('competitions.detail.tabs.award') })
</script>

<template>
  <CompetitionsIssuerAwardWorkspace />
</template>
