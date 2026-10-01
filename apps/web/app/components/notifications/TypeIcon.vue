<script setup lang="ts">
import type { Component } from 'vue'
import { Bell, Flag, Mail, MessageSquare, Plug, ReceiptText, RefreshCw, Tag, Timer, TrendingUp, Trophy } from '@lucide/vue'
import type { NotificationIconGroup } from '~/utils/notification-route'

/** Notification type icon (SCREENS S11 `notifications.type.*` groups); the BAFO round uses the mark. */
const props = defineProps<{ type: string }>()

const ICONS: Record<Exclude<NotificationIconGroup, 'bafo'>, Component> = {
  invitation: Mail,
  update: RefreshCw,
  timer: Timer,
  flag: Flag,
  offer: Tag,
  standing: TrendingUp,
  award: Trophy,
  qa: MessageSquare,
  billing: ReceiptText,
  integrations: Plug,
  other: Bell,
}

const group = computed(() => notificationIconGroup(props.type))
</script>

<template>
  <span
    class="flex size-9 shrink-0 items-center justify-center rounded-full"
    :class="group === 'bafo' ? 'bg-fg' : 'bg-neutral-soft text-neutral-soft-fg'"
    aria-hidden="true"
  >
    <AppBrandMark
      v-if="group === 'bafo'"
      size-class="size-4"
    />
    <component
      :is="ICONS[group]"
      v-else
      :size="16"
    />
  </span>
</template>
