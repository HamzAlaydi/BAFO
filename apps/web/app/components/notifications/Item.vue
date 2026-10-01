<script setup lang="ts">
import type { Notification } from '~/types/api/notifications'

/**
 * One notification (bell menu and W29): type icon, title, body, relative time and an unread dot.
 * Activating it emits `open`; the parent marks it read and navigates by its `route` (SCREENS S11).
 * Title and body are rendered by the server in the request locale (never other participants' data).
 */
defineProps<{
  notification: Notification
  compact?: boolean
}>()

const emit = defineEmits<{ open: [notification: Notification] }>()
const { t } = useI18n()
</script>

<template>
  <div class="flex items-start gap-3">
    <NotificationsTypeIcon :type="notification.type" />
    <button
      type="button"
      class="min-w-0 flex-1 rounded-sm text-start focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
      @click="emit('open', notification)"
    >
      <span class="flex items-start gap-2">
        <span
          class="min-w-0 flex-1 text-sm text-fg"
          :class="notification.read_at ? 'font-medium' : 'font-bold'"
        >{{ notification.title }}</span>
        <span
          v-if="!notification.read_at"
          class="mt-1.5 size-2 shrink-0 rounded-full bg-brand"
          aria-hidden="true"
        />
      </span>
      <span
        class="mt-0.5 block text-sm text-fg-muted"
        :class="compact && 'line-clamp-2'"
      >{{ notification.body }}</span>
      <span class="mt-1 flex items-center gap-2 text-xs text-fg-muted">
        <UiRelativeTime :value="notification.created_at" />
        <span
          v-if="!notification.read_at"
          class="sr-only"
        >{{ t('notifications.unread_label') }}</span>
      </span>
    </button>
    <slot name="actions" />
  </div>
</template>
