<script setup lang="ts">
import type { IssuerCompetition } from '~/types/api/competitions'

/**
 * W16 Edit after publish · `/dashboard/competitions/{id}/edit` (issuer; SCREENS §2.4). Drafts are
 * edited in the setup wizard, so a draft redirects there.
 */
const ctx = useCompetitionContext()
const { t } = useI18n()
const localePath = useLocalePath()

const competition = computed(() => (ctx.competition.value?.viewer_role === 'issuer' ? ctx.competition.value as IssuerCompetition : null))
useSeoMeta({ title: () => t('competitions.issuer.edit.title') })

watch(competition, (current) => {
  if (current?.status === 'draft') void navigateTo(localePath(`/dashboard/competitions/${current.id}/setup`), { replace: true })
}, { immediate: true })

function onSaved(updated: IssuerCompetition): void {
  ctx.competition.value = updated
  void navigateTo(localePath(`/dashboard/competitions/${updated.id}`))
}
</script>

<template>
  <CompetitionsIssuerEditForm
    v-if="competition && competition.status !== 'draft'"
    :competition="competition"
    @saved="onSaved"
  />
</template>
