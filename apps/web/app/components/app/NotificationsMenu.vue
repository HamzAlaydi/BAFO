<script setup lang="ts">
import { Bell, BellOff, CheckCheck } from '@lucide/vue'
import type { Notification } from '~/types/api/notifications'

/**
 * The bell (SCREENS §2.3): unread badge, the latest 10, "Mark all as read" and "View all".
 * The count follows the private user channel through `useNotificationsStore`.
 */
const { t } = useI18n()
const store = useNotificationsStore()
const localePath = useLocalePath()
const toast = useToast()
const { message } = useErrorMessage()
const markingAll = ref(false)

const badge = computed(() => (store.unreadCount > 99 ? '99+' : String(store.unreadCount)))

function onOpenChange(open: boolean): void {
  if (open && !store.loadingLatest) void store.loadLatest()
}

async function openNotification(notification: Notification, close: () => void): Promise<void> {
  close()
  try {
    await store.markRead(notification)
  }
  catch {
    // Navigation still happens; the unread state is corrected by the next sync.
  }
  await navigateTo(localePath(notificationPath(notification.route)))
}

async function markAll(): Promise<void> {
  markingAll.value = true
  try {
    await store.markAllRead()
  }
  catch (error) {
    toast.error(message(error))
  }
  finally {
    markingAll.value = false
  }
}
</script>

<template>
  <UiDropdownMenu
    :label="t('notifications.title')"
    width="lg"
    trigger-class="relative size-10 justify-center text-fg-muted hover:bg-surface-muted hover:text-fg"
    @update:open="onOpenChange"
  >
    <template #trigger>
      <span class="sr-only">{{ t('notifications.title') }}</span>
      <Bell
        :size="20"
        aria-hidden="true"
      />
      <span
        v-if="store.unreadCount > 0"
        class="absolute end-1 top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[0.625rem] font-bold text-primary-fg tabular-nums"
      >
        <span aria-hidden="true">{{ badge }}</span>
        <span class="sr-only">{{ t('notifications.unread_count', { count: store.unreadCount }, store.unreadCount) }}</span>
      </span>
    </template>
    <template #panel="{ close }">
      <div class="flex items-center justify-between gap-3 border-b border-line px-4 py-3">
        <p class="font-bold text-fg">
          {{ t('notifications.title') }}
        </p>
        <UiButton
          v-if="store.unreadCount > 0"
          variant="ghost"
          size="sm"
          :icon="CheckCheck"
          :loading="markingAll"
          @click="markAll"
        >
          {{ t('notifications.mark_all_read') }}
        </UiButton>
      </div>
      <div class="max-h-[min(28rem,70dvh)] overflow-y-auto">
        <div
          v-if="store.loadingLatest && !store.latestLoaded"
          class="flex flex-col gap-4 p-4"
        >
          <div
            v-for="n in 3"
            :key="n"
            class="flex gap-3"
          >
            <UiSkeleton
              shape="circle"
              class="size-9"
            />
            <UiSkeleton
              :lines="2"
              class="flex-1"
            />
          </div>
        </div>
        <UiErrorState
          v-else-if="store.latestError && store.latest.length === 0"
          :error="store.latestError"
          :retrying="store.loadingLatest"
          compact
          @retry="store.loadLatest()"
        />
        <UiEmptyState
          v-else-if="store.latest.length === 0"
          :icon="BellOff"
          :title="t('notifications.list.empty.title')"
          :description="t('notifications.list.empty.body')"
          compact
        />
        <ul
          v-else
          class="divide-y divide-line"
        >
          <li
            v-for="notification in store.latest"
            :key="notification.id"
            class="px-4 py-3"
            :class="!notification.read_at && 'bg-primary-soft/40'"
          >
            <NotificationsItem
              :notification="notification"
              compact
              @open="openNotification($event, close)"
            />
          </li>
        </ul>
      </div>
      <div class="border-t border-line p-2">
        <UiButton
          to="/dashboard/notifications"
          variant="ghost"
          size="sm"
          block
          @click="close()"
        >
          {{ t('notifications.view_all') }}
        </UiButton>
      </div>
    </template>
  </UiDropdownMenu>
</template>
