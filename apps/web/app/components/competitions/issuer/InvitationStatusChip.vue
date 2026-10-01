<script setup lang="ts">
import type { Component } from 'vue'
import { Ban, Clock, Eye, PencilLine, Send, UserCheck, UserX } from '@lucide/vue'
import type { InvitationStatus } from '~/types/api/competitions'
import type { Tone } from '~/types/ui'

/** Invitation status chip for the issuer (SCREENS S2 "Other chips"): icon and text, never colour alone. */
const props = withDefaults(defineProps<{ status: InvitationStatus, size?: 'sm' | 'md' }>(), { size: 'sm' })
const { t } = useI18n()

const VISUAL: Record<InvitationStatus, { tone: Tone, icon: Component }> = {
  draft: { tone: 'neutral', icon: PencilLine },
  sent: { tone: 'info', icon: Send },
  viewed: { tone: 'info', icon: Eye },
  joined: { tone: 'primary', icon: UserCheck },
  declined: { tone: 'neutral', icon: UserX },
  revoked: { tone: 'neutral', icon: Ban },
  expired: { tone: 'neutral', icon: Clock },
}

const visual = computed(() => VISUAL[props.status] ?? VISUAL.draft)
</script>

<template>
  <UiBadge
    :tone="visual.tone"
    :icon="visual.icon"
    :size="size"
  >
    {{ t(`invitations.status.${status}`) }}
  </UiBadge>
</template>
