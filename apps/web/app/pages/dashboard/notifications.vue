<script setup lang="ts">
import { BellOff, Check, CheckCheck, EllipsisVertical, Trash2 } from '@lucide/vue'
import { listNotifications } from '~/services/notifications'
import type { PagePagination } from '~/types/api/common'
import type { Notification } from '~/types/api/notifications'
import type { MenuEntry } from '~/types/ui'

/**
 * W29 Notifications · `/dashboard/notifications` (SCREENS §2.4): All / Unread, newest first, paged.
 * Opening one marks it read and follows its route (S11). Row menu: mark read, delete; header: mark
 * all read, delete all (confirmed). New notifications arrive on page 1 in real time.
 */
definePageMeta({ layout: 'dashboard', middleware: 'auth' })

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const localePath = useLocalePath()
const store = useNotificationsStore()
const toast = useToast()
const { message } = useErrorMessage()

useSeoMeta({ title: () => t('notifications.title') })

type Filter = 'all' | 'unread'
const filter = ref<Filter>(route.query.filter === 'unread' ? 'unread' : 'all')
const page = ref(Math.max(1, Number(route.query.page) || 1))
const items = ref<Notification[]>([])
const pagination = ref<PagePagination | null>(null)
const loading = ref(true)
const loadError = ref<unknown>(null)
const markingAll = ref(false)
const deleteAllOpen = ref(false)
const deletingAll = ref(false)
const deleteAllError = ref<string | null>(null)

const filterOptions = computed(() => [
  { value: 'all' as const, label: t('notifications.filters.all') },
  { value: 'unread' as const, label: t('notifications.filters.unread') },
])

let requestSeq = 0
async function load(): Promise<void> {
  const seq = ++requestSeq
  loading.value = true
  loadError.value = null
  try {
    const result = await listNotifications({ unread: filter.value === 'unread', page: page.value })
    if (seq !== requestSeq) return
    items.value = result.items
    pagination.value = result.pagination
    store.applyUnreadCount({ unread_count: result.unread_count })
  }
  catch (error) {
    if (seq === requestSeq) loadError.value = error
  }
  finally {
    if (seq === requestSeq) loading.value = false
  }
}

onMounted(load)

watch([filter, page], ([nextFilter, nextPage], [previousFilter]) => {
  if (nextFilter !== previousFilter && nextPage !== 1) {
    page.value = 1
    return
  }
  void router.replace({ query: { ...route.query, filter: nextFilter === 'unread' ? 'unread' : undefined, page: nextPage > 1 ? String(nextPage) : undefined } })
  void load()
})

// Realtime: new notifications join the first page.
store.onCreated((notification) => {
  if (page.value !== 1) return
  if (!items.value.some(item => item.id === notification.id)) {
    items.value = [notification, ...items.value].slice(0, pagination.value?.per_page ?? 20)
  }
})

const pageCount = computed(() => pagination.value?.last_page ?? (pagination.value?.has_more ? page.value + 1 : page.value))

function replaceItem(updated: Notification): void {
  items.value = items.value.map(item => (item.id === updated.id ? { ...item, read_at: updated.read_at } : item))
}

async function open(notification: Notification): Promise<void> {
  try {
    replaceItem(await store.markRead(notification))
  }
  catch {
    // Navigation still happens; the count is corrected by the next sync.
  }
  await navigateTo(localePath(notificationPath(notification.route)))
}

async function markRead(notification: Notification): Promise<void> {
  try {
    replaceItem(await store.markRead(notification))
  }
  catch (error) {
    toast.error(message(error))
  }
}

async function remove(notification: Notification): Promise<void> {
  try {
    await store.remove(notification)
    items.value = items.value.filter(item => item.id !== notification.id)
    if (items.value.length === 0 && page.value > 1) page.value -= 1
    else if (items.value.length === 0) void load()
  }
  catch (error) {
    toast.error(message(error))
  }
}

async function markAll(): Promise<void> {
  markingAll.value = true
  try {
    await store.markAllRead()
    const now = new Date().toISOString()
    items.value = filter.value === 'unread' ? [] : items.value.map(item => (item.read_at ? item : { ...item, read_at: now }))
  }
  catch (error) {
    toast.error(message(error))
  }
  finally {
    markingAll.value = false
  }
}

async function deleteAll(): Promise<void> {
  deletingAll.value = true
  deleteAllError.value = null
  try {
    await store.removeAll()
    items.value = []
    pagination.value = null
    page.value = 1
    deleteAllOpen.value = false
    toast.success(t('notifications.delete_all.done'))
  }
  catch (error) {
    deleteAllError.value = message(error)
  }
  finally {
    deletingAll.value = false
  }
}

function rowMenu(notification: Notification): MenuEntry[] {
  return [
    ...(notification.read_at ? [] : [{ key: 'read', label: t('notifications.mark_read'), icon: Check }]),
    { key: 'delete', label: t('notifications.delete'), icon: Trash2, danger: true },
  ]
}

function onRowAction(notification: Notification, key: string): void {
  if (key === 'read') void markRead(notification)
  else if (key === 'delete') void remove(notification)
}
</script>

<template>
  <div class="flex flex-col gap-6">
    <UiPageHeader
      :title="t('notifications.title')"
      :description="t('notifications.subtitle')"
    >
      <template #actions>
        <UiButton
          variant="secondary"
          :icon="CheckCheck"
          :loading="markingAll"
          :disabled="store.unreadCount === 0"
          @click="markAll"
        >
          {{ t('notifications.mark_all_read') }}
        </UiButton>
        <UiButton
          variant="danger-ghost"
          :icon="Trash2"
          :disabled="items.length === 0 && !loading"
          @click="deleteAllOpen = true"
        >
          {{ t('notifications.delete_all.open') }}
        </UiButton>
      </template>
    </UiPageHeader>

    <UiSegmented
      v-model="filter"
      :options="filterOptions"
      :label="t('notifications.filters.label')"
      size="sm"
    />

    <UiCard padding="none">
      <div
        v-if="loading && items.length === 0"
        class="flex flex-col gap-5 p-5"
        aria-busy="true"
        :aria-label="t('common.loading')"
      >
        <div
          v-for="n in 5"
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
        v-else-if="loadError && items.length === 0"
        :error="loadError"
        :retrying="loading"
        @retry="load"
      />
      <UiEmptyState
        v-else-if="items.length === 0"
        :icon="BellOff"
        :title="filter === 'unread' ? t('notifications.list.empty_unread.title') : t('notifications.list.empty.title')"
        :description="filter === 'unread' ? t('notifications.list.empty_unread.body') : t('notifications.list.empty.body')"
      />
      <ul
        v-else
        class="divide-y divide-line"
        :aria-busy="loading || undefined"
      >
        <li
          v-for="notification in items"
          :key="notification.id"
          class="px-4 py-4 sm:px-5"
          :class="!notification.read_at && 'bg-primary-soft/40'"
        >
          <NotificationsItem
            :notification="notification"
            @open="open"
          >
            <template #actions>
              <UiDropdownMenu
                :label="t('notifications.row_actions', { title: notification.title })"
                :items="rowMenu(notification)"
                trigger-class="size-9 justify-center text-fg-muted hover:bg-surface-muted hover:text-fg"
                @select="onRowAction(notification, $event)"
              >
                <template #trigger>
                  <EllipsisVertical
                    :size="18"
                    aria-hidden="true"
                  />
                  <span class="sr-only">{{ t('notifications.row_actions', { title: notification.title }) }}</span>
                </template>
              </UiDropdownMenu>
            </template>
          </NotificationsItem>
        </li>
      </ul>
    </UiCard>

    <UiPagination
      v-if="pageCount > 1"
      v-model:page="page"
      :page-count="pageCount"
    />

    <UiConfirmDialog
      v-model:open="deleteAllOpen"
      :title="t('notifications.delete_all.title')"
      :description="t('notifications.delete_all.description')"
      :confirm-label="t('notifications.delete_all.confirm')"
      :busy="deletingAll"
      :error="deleteAllError"
      danger
      @confirm="deleteAll"
    />
  </div>
</template>
