<script setup lang="ts">
import type { IssuerCompetition } from '~/types/api/competitions'

/**
 * Wizard step 5 «المستندات» (SCREENS W15 `documents`): uploads with kind, title and progress, external
 * links, order and removal. Each change is saved immediately by its own endpoint.
 */
defineProps<{ competition: IssuerCompetition }>()
const emit = defineEmits<{ changed: [] }>()
const { t } = useI18n()
</script>

<template>
  <div class="flex flex-col gap-4">
    <UiAlert tone="info">
      {{ t('competitions.setup.documents.intro') }}
    </UiAlert>
    <CompetitionsIssuerAttachmentManager
      :competition-id="competition.id"
      :status="competition.status"
      :can-manage="competition.permissions.can_edit"
      @changed="emit('changed')"
    />
  </div>
</template>
