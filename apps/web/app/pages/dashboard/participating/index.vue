<script setup lang="ts">
import { Handshake, SearchX } from '@lucide/vue'
import { declineInvitation, listParticipantCompetitions } from '~/services/competitions'
import type { PagePagination } from '~/types/api/common'
import type { Competition, CompetitionStatusGroup, Direction, ParticipantCompetitionListItem } from '~/types/api/competitions'
import type { TabItem } from '~/types/ui'

/**
 * W23 Participating · `/dashboard/participating` (SCREENS §2.4): the competitions the organisation
 * is invited to or takes part in, `GET /competitions?role=participant`, by status group (Active,
 * Ended, All), direction and title search. Filters live in the query string (non-sensitive), so
 * the Home tiles can link to a tab. Cards needing action (`access.state` join_required or
 * plan_required) come first within the loaded page and offer Join / Decline (the W14 dialogs).
 * The list refreshes on window focus and on competition notifications.
 */
definePageMeta({ layout: 'dashboard', middleware: 'auth' })

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const localePath = useLocalePath()
const toast = useToast()
const notifications = useNotificationsStore()
const { message } = useErrorMessage()

useSeoMeta({ title: () => t('competitions.participating.title') })

type Group = Exclude<CompetitionStatusGroup, 'draft'>
type DirectionFilter = 'all' | Direction

function queryString(value: unknown): string | null {
  return typeof value === 'string' ? value : null
}

function groupFrom(value: unknown): Group {
  const text = queryString(value)
  return text === 'ended' || text === 'all' ? text : 'active'
}

function directionFrom(value: unknown): DirectionFilter {
  const text = queryString(value)
  return text === 'tender' || text === 'auction' ? text : 'all'
}

const group = ref<Group>(groupFrom(route.query.status_group))
const direction = ref<DirectionFilter>(directionFrom(route.query.direction))
const search = ref((queryString(route.query.q) ?? '').slice(0, 100))
const page = ref(Math.max(1, Number(route.query.page) || 1))

const items = ref<ParticipantCompetitionListItem[]>([])
const pagination = ref<PagePagination | null>(null)
const loading = ref(true)
const loadError = ref<unknown>(null)

const GROUPS: readonly Group[] = ['active', 'ended', 'all']
const groupTabs = computed<TabItem[]>(() => GROUPS.map(key => ({ key, label: t(`competitions.participating.tabs.${key}`) })))
const groupModel = computed({
  get: () => group.value,
  set: (value: string) => {
    group.value = groupFrom(value)
  },
})

const directionOptions = computed(() => [
  { value: 'all' as const, label: t('competitions.participating.direction_filter.all') },
  { value: 'tender' as const, label: t('competitions.direction.tender') },
  { value: 'auction' as const, label: t('competitions.direction.auction') },
])

const sorted = computed(() => sortParticipating(items.value))
const actionItems = computed(() => sorted.value.filter(needsAction))
const otherItems = computed(() => sorted.value.filter(item => !needsAction(item)))
const filtered = computed(() => search.value.trim() !== '' || direction.value !== 'all')
const pageCount = computed(() => pagination.value?.last_page ?? (pagination.value?.has_more ? page.value + 1 : page.value))

let seq = 0
async function load(): Promise<void> {
  const current = ++seq
  loading.value = true
  loadError.value = null
  try {
    const result = await listParticipantCompetitions({
      status_group: group.value,
      direction: direction.value === 'all' ? undefined : direction.value,
      q: search.value || undefined,
      page: page.value,
    })
    if (current !== seq) return
    items.value = result.items
    pagination.value = result.pagination
  }
  catch (error) {
    if (current === seq) loadError.value = error
  }
  finally {
    if (current === seq) loading.value = false
  }
}

onMounted(load)

watch([group, direction, search], () => {
  if (page.value !== 1) page.value = 1
  else void syncAndLoad()
})
watch(page, () => void syncAndLoad())

async function syncAndLoad(): Promise<void> {
  await router.replace({
    query: {
      ...route.query,
      status_group: group.value === 'active' ? undefined : group.value,
      direction: direction.value === 'all' ? undefined : direction.value,
      q: search.value || undefined,
      page: page.value > 1 ? String(page.value) : undefined,
    },
  })
  await load()
}

// Refresh quietly when the organisation's competitions change elsewhere.
const refreshSoon = useDebounceFn(() => load(), 2000)
notifications.onCreated((notification) => {
  if (/^(competition|invitation|offer|standing|bafo|award)\./.test(notification.type)) void refreshSoon()
})
const focused = useWindowFocus()
watch(focused, (isFocused) => {
  if (isFocused && !loading.value) void refreshSoon()
})

// ---------- Join and decline (the W14 dialogs) ----------

const joinTarget = ref<ParticipantCompetitionListItem | null>(null)
const joinOpen = ref(false)
const declineTarget = ref<ParticipantCompetitionListItem | null>(null)
const declineOpen = ref(false)
const declining = ref(false)
const declineError = ref<string | null>(null)

function openJoin(item: ParticipantCompetitionListItem): void {
  joinTarget.value = item
  joinOpen.value = true
}

function openDecline(item: ParticipantCompetitionListItem): void {
  declineTarget.value = item
  declineError.value = null
  declineOpen.value = true
}

async function onJoined(competition: Competition): Promise<void> {
  toast.success(t('invitations.join.joined'))
  await navigateTo(localePath(`/dashboard/competitions/${competition.id}`))
}

async function decline(reason: string | null): Promise<void> {
  const target = declineTarget.value
  if (!target) return
  declining.value = true
  declineError.value = null
  try {
    await declineInvitation(target.invitation.id, reason)
    declineOpen.value = false
    toast.success(t('invitations.decline.done_title'))
    await load()
  }
  catch (error) {
    if (error instanceof ApiError && error.code === 'invalid_state_transition') {
      declineError.value = t('invitations.decline.not_possible')
      void load()
    }
    else {
      declineError.value = message(error)
    }
  }
  finally {
    declining.value = false
  }
}
</script>

<template>
  <div class="flex flex-col gap-6">
    <UiPageHeader
      :title="t('competitions.participating.title')"
      :description="t('competitions.participating.subtitle')"
    />

    <UiTabs
      v-model="groupModel"
      :items="groupTabs"
      :label="t('competitions.participating.tabs.label')"
    >
      <div class="flex flex-col gap-6">
        <div class="flex flex-wrap items-end gap-x-6 gap-y-4">
          <UiSegmented
            v-model="direction"
            :options="directionOptions"
            :label="t('competitions.participating.direction_filter.label')"
            size="sm"
            class="shrink-0"
          />
          <div class="w-full sm:ms-auto sm:w-72">
            <UiSearchInput
              v-model="search"
              :label="t('competitions.participating.search')"
              :placeholder="t('competitions.participating.search')"
            />
          </div>
        </div>

        <div
          v-if="loading && items.length === 0"
          class="flex flex-col gap-4"
          aria-busy="true"
          :aria-label="t('common.loading')"
        >
          <div
            v-for="n in 3"
            :key="n"
            class="flex flex-col gap-3 rounded-lg border border-line bg-surface p-5"
          >
            <div class="flex items-center gap-3">
              <UiSkeleton
                shape="circle"
                class="size-8"
              />
              <UiSkeleton class="h-4 w-40" />
            </div>
            <UiSkeleton class="h-5 w-3/4" />
            <UiSkeleton class="h-4 w-1/2" />
          </div>
        </div>

        <UiErrorState
          v-else-if="loadError && items.length === 0"
          :error="loadError"
          :retrying="loading"
          @retry="load"
        />

        <UiCard
          v-else-if="items.length === 0"
          padding="none"
        >
          <UiEmptyState
            :icon="filtered ? SearchX : Handshake"
            :title="filtered ? t('competitions.participating.empty_filtered.title') : t('competitions.participating.empty.title')"
            :description="filtered ? t('competitions.participating.empty_filtered.body') : t('competitions.participating.empty.body')"
          />
        </UiCard>

        <div
          v-else
          class="flex flex-col gap-6"
          :aria-busy="loading || undefined"
        >
          <UiAlert
            v-if="loadError"
            tone="warning"
          >
            {{ t('common.states.stale_data') }}
          </UiAlert>
          <section
            v-if="actionItems.length > 0"
            class="flex flex-col gap-3"
            aria-labelledby="participating-needs-action"
          >
            <h2
              id="participating-needs-action"
              class="text-sm font-bold text-fg"
            >
              {{ t('competitions.participating.needs_action', { count: actionItems.length }, actionItems.length) }}
            </h2>
            <ul class="flex flex-col gap-4">
              <li
                v-for="item in actionItems"
                :key="item.id"
              >
                <CompetitionsParticipantCompetitionCard
                  :item="item"
                  @join="openJoin(item)"
                  @decline="openDecline(item)"
                />
              </li>
            </ul>
          </section>
          <section
            v-if="otherItems.length > 0"
            class="flex flex-col gap-3"
            :aria-label="t('competitions.participating.list_label')"
          >
            <h2
              v-if="actionItems.length > 0"
              class="text-sm font-bold text-fg"
            >
              {{ t('competitions.participating.others') }}
            </h2>
            <ul class="flex flex-col gap-4">
              <li
                v-for="item in otherItems"
                :key="item.id"
              >
                <CompetitionsParticipantCompetitionCard
                  :item="item"
                  @join="openJoin(item)"
                  @decline="openDecline(item)"
                />
              </li>
            </ul>
          </section>
        </div>

        <UiPagination
          v-if="pageCount > 1"
          v-model:page="page"
          :page-count="pageCount"
        />
      </div>
    </UiTabs>

    <CompetitionsParticipantJoinDialog
      v-if="joinTarget"
      v-model:open="joinOpen"
      :invitation-id="joinTarget.invitation.id"
      :competition-id="joinTarget.id"
      @joined="onJoined"
      @plan-required="load"
      @stale="load"
    />
    <InvitationsDeclineDialog
      v-model:open="declineOpen"
      :busy="declining"
      :error="declineError"
      @confirm="decline"
    />
  </div>
</template>
