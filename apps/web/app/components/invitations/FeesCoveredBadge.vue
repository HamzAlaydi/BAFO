<script setup lang="ts">
import { Ticket } from '@lucide/vue'

/**
 * «رسوم مغطّاة» / "Fees covered" (SCREENS S2): info tone with a ticket icon. Shown only to the covered
 * participant or invitee, never to others. With `sponsorName`: «رسوم مغطّاة · تغطيها {sponsor}».
 */
defineProps<{
  sponsorName?: string | null
  size?: 'sm' | 'md'
}>()

const { t } = useI18n()
</script>

<template>
  <UiBadge
    tone="info"
    :icon="Ticket"
    :size="size ?? 'md'"
  >
    {{ sponsorName ? t('sponsorship.badge.fees_covered_by', { sponsor: sponsorName }) : t('sponsorship.badge.fees_covered') }}
  </UiBadge>
</template>
