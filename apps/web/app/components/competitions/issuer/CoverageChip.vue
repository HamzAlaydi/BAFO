<script setup lang="ts">
import { BadgeCheck, Minus, Ticket } from '@lucide/vue'
import type { Coverage, PassStatus } from '~/types/api/competitions'

/**
 * Fee coverage of an invitation, issuer view only (SCREENS S2): «مغطّاة منكم» (info, ticket),
 * «باقة المتنافس» or «غير مغطّاة» (neutral), plus the sponsored pass status when one exists.
 */
defineProps<{ coverage: Coverage, passStatus?: PassStatus | null }>()
const { t } = useI18n()
</script>

<template>
  <span class="inline-flex flex-wrap items-center gap-1.5">
    <UiBadge
      :tone="coverage === 'sponsored' ? 'info' : 'neutral'"
      :icon="coverage === 'sponsored' ? Ticket : coverage === 'own_plan' ? BadgeCheck : Minus"
      size="sm"
    >
      {{ t(`sponsorship.coverage.${coverage}`) }}
    </UiBadge>
    <UiBadge
      v-if="passStatus"
      :tone="passStatus === 'joined' ? 'primary' : 'neutral'"
      size="sm"
    >
      {{ t(`sponsorship.pass_status.${passStatus}`) }}
    </UiBadge>
  </span>
</template>
