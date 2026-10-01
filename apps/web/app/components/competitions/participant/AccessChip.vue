<script setup lang="ts">
import type { Component } from 'vue'
import { CircleCheck, CircleSlash, CreditCard, Eye, MailOpen } from '@lucide/vue'
import type { AccessState } from '~/types/api/competitions'
import type { Tone } from '~/types/ui'

/**
 * Access state chip (SCREENS S2 `invitations.access.*`), from the server's `access.state` only
 * (ARCHITECTURE §8.4: the client never computes entitlement). Icon and text on every tone.
 */
const props = withDefaults(defineProps<{
  state: AccessState
  size?: 'sm' | 'md'
}>(), {
  size: 'md',
})

const { t } = useI18n()

const VISUALS: Record<AccessState, { tone: Tone, icon: Component }> = {
  join_required: { tone: 'info', icon: MailOpen },
  plan_required: { tone: 'warning', icon: CreditCard },
  full: { tone: 'primary', icon: CircleCheck },
  read_only: { tone: 'neutral', icon: Eye },
  unavailable: { tone: 'neutral', icon: CircleSlash },
}

const visual = computed(() => VISUALS[props.state])
</script>

<template>
  <UiBadge
    :tone="visual.tone"
    :icon="visual.icon"
    :size="size"
  >
    {{ t(`invitations.access.${state}`) }}
  </UiBadge>
</template>
