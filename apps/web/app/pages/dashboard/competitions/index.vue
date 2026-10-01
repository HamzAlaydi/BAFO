<script setup lang="ts">
import { ClipboardList, Plus, X } from '@lucide/vue'
import { CREATE_COMPETITION_ACTION } from '~/config/navigation'
import { listIssuerCompetitions } from '~/services/competitions'
import type { PagePagination } from '~/types/api/common'
import type { CompetitionSort, CompetitionStatus, CompetitionStatusGroup, Direction, IssuerCompetitionListItem } from '~/types/api/competitions'
import type { TabItem, TableColumn } from '~/types/ui'

/**
 * W11 My competitions · `/dashboard/competitions` (SCREENS §2.4): `GET /competitions?role=issuer` with
 * tabs by `status_group` (Active, Drafts, Ended, All), a filter bar (direction, debounced search,
 * sort) and the table: reference, title, direction, status (+ closing soon / extended), opening or
 * closing (a countdown while live), invited · joined · with offers, leading offer, last update.
 * Drafts open the setup wizard at the first incomplete step; others open the detail.
 *
 * Filters live in the query string (shareable; `status=` also arrives from the home tiles).
 */
definePageMeta({ layout: 'dashboard', middleware: 'auth' })

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

useSeoMeta({ title: () => t('competitions.list.title') })

const GROUPS: CompetitionStatusGroup[] = ['active', 'draft', 'ended', 'all']
const SORTS: CompetitionSort[] = ['-updated_at', 'effective_close_at', '-created_at']
const STATUSES: CompetitionStatus[] = ['draft', 'scheduled', 'live', 'closed', 'bafo_round', 'awarded', 'not_awarded', 'cancelled']

function queryString(key: string): string | null {
  const value = route.query[key]
  return typeof value === 'string' ? value : null
}

const group = computed<CompetitionStatusGroup>(() => {
  const value = queryString('status_group')
  if (value && (GROUPS as string[]).includes(value)) return value as CompetitionStatusGroup
  return statusFilter.value.length > 0 ? 'all' : 'active'
})
const statusFilter = computed<CompetitionStatus[]>(() => (queryString('status') ?? '').split(',').filter((value): value is CompetitionStatus => (STATUSES as string[]).includes(value)))
const direction = computed<'all' | Direction>(() => {
  const value = queryString('direction')
  return value === 'tender' || value === 'auction' ? value : 'all'
})
const search = computed(() => queryString('q') ?? '')
const sort = computed<CompetitionSort>(() => {
  const value = queryString('sort')
  return value && (SORTS as string[]).includes(value) ? value as CompetitionSort : '-updated_at'
})
const page = computed(() => Math.max(1, Number(queryString('page') ?? 1) || 1))

function setQuery(patch: Record<string, string | null | undefined>, resetPage = true): void {
  const merged = new Map<string, string>()
  for (const [key, value] of Object.entries(route.query)) {
    if (typeof value === 'string') merged.set(key, value)
  }
  for (const [key, value] of Object.entries(patch)) {
    if (value === null || value === undefined || value === '') merged.delete(key)
    else merged.set(key, value)
  }
  if (resetPage && !('page' in patch)) merged.delete('page')
  void router.replace({ query: Object.fromEntries(merged) })
}

const groupModel = computed({
  get: () => group.value,
  set: (value: string) => setQuery({ status_group: value, status: null }),
})
const directionModel = computed<'all' | Direction | null>({
  get: () => direction.value,
  set: value => setQuery({ direction: value === 'all' ? null : value }),
})
const searchModel = computed({
  get: () => search.value,
  set: (value: string) => setQuery({ q: value.trim() || null }),
})
const sortModel = computed<CompetitionSort | null>({
  get: () => sort.value,
  set: value => setQuery({ sort: value === '-updated_at' ? null : value }),
})
const pageModel = computed({
  get: () => page.value,
  set: (value: number) => setQuery({ page: value > 1 ? String(value) : null }, false),
})

// ---------- Data ----------

const items = ref<IssuerCompetitionListItem[]>([])
const pagination = ref<PagePagination | null>(null)
const loading = ref(true)
const loadError = ref<unknown>(null)
let seq = 0

async function load(): Promise<void> {
  const current = ++seq
  loading.value = true
  loadError.value = null
  try {
    const result = await listIssuerCompetitions({
      status_group: statusFilter.value.length > 0 ? undefined : group.value,
      status: statusFilter.value.length > 0 ? statusFilter.value : undefined,
      direction: direction.value === 'all' ? undefined : direction.value,
      q: search.value || undefined,
      sort: sort.value,
      page: page.value,
      per_page: 20,
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

watch(() => route.query, () => void load(), { immediate: true, deep: true })

// ---------- View ----------

const canCreate = computed(() => auth.can(CREATE_COMPETITION_ACTION.permission))
const canIssue = computed(() => auth.entitlements?.can_issue === true)
const createEnabled = computed(() => canCreate.value && canIssue.value)
const createHintId = useId()

const tabs = computed<TabItem[]>(() => GROUPS.map(key => ({ key, label: t(`competitions.list.tabs.${key}`) })))
const directionOptions = computed(() => [
  { value: 'all' as const, label: t('competitions.list.filters.all_directions') },
  { value: 'tender' as const, label: t('competitions.direction.tender') },
  { value: 'auction' as const, label: t('competitions.direction.auction') },
])
const sortOptions = computed(() => SORTS.map(value => ({ value, label: t(`competitions.list.sort.${value.replace('-', '')}`) })))

const columns = computed<TableColumn[]>(() => [
  { key: 'title', label: t('competitions.list.columns.title'), primary: true, class: 'min-w-56 max-w-sm' },
  { key: 'direction', label: t('competitions.list.columns.direction'), hideOnMobile: true },
  { key: 'status', label: t('competitions.list.columns.status') },
  { key: 'timing', label: t('competitions.list.columns.timing'), class: 'whitespace-nowrap' },
  { key: 'counts', label: t('competitions.list.columns.counts'), numeric: true, class: 'whitespace-nowrap' },
  { key: 'leading', label: t('glossary.leading_offer'), numeric: true, class: 'whitespace-nowrap' },
  { key: 'updated', label: t('competitions.list.columns.updated'), hideOnMobile: true, hideBelow: '2xl' },
])

const pageCount = computed(() => pagination.value?.last_page ?? (pagination.value?.has_more ? page.value + 1 : page.value))

const asItem = (row: unknown) => row as IssuerCompetitionListItem
const linkFor = (item: IssuerCompetitionListItem) => (item.status === 'draft' ? `/dashboard/competitions/${item.id}/setup` : `/dashboard/competitions/${item.id}`)

const emptyKey = computed(() => (group.value === 'all' || statusFilter.value.length > 0 || search.value || direction.value !== 'all' ? 'filtered' : group.value === 'draft' ? 'drafts' : group.value))
</script>

<template>
  <div class="flex flex-col gap-6">
    <UiPageHeader
      :title="t('competitions.list.title')"
      :description="t('competitions.list.subtitle')"
    >
      <template
        v-if="canCreate"
        #actions
      >
        <div class="flex flex-col items-end gap-1">
          <UiButton
            :to="createEnabled ? CREATE_COMPETITION_ACTION.to : undefined"
            :icon="Plus"
            :disabled="!createEnabled"
            :aria-describedby="createEnabled ? undefined : createHintId"
          >
            {{ t('nav.create_competition') }}
          </UiButton>
          <p
            v-if="!canIssue"
            :id="createHintId"
            class="max-w-xs text-end text-xs text-fg-muted"
          >
            {{ t('nav.create_competition_requires_plan') }}
            <NuxtLinkLocale
              to="/dashboard/billing/plans"
              class="link"
            >
              {{ t('competitions.list.view_plans') }}
            </NuxtLinkLocale>
          </p>
        </div>
      </template>
    </UiPageHeader>

    <UiTabs
      v-model="groupModel"
      :items="tabs"
      :label="t('competitions.list.tabs.label')"
    >
      <div class="flex flex-col gap-4">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end">
          <div class="min-w-0 flex-1">
            <UiSearchInput
              v-model="searchModel"
              :label="t('competitions.list.filters.search')"
              :placeholder="t('competitions.list.filters.search_placeholder')"
            />
          </div>
          <UiSegmented
            v-model="directionModel"
            :options="directionOptions"
            :label="t('competitions.list.filters.direction')"
            size="sm"
          />
          <div class="lg:w-56">
            <UiSelect
              v-model="sortModel"
              :options="sortOptions"
              :label="t('competitions.list.filters.sort')"
            />
          </div>
        </div>

        <div
          v-if="statusFilter.length > 0"
          class="flex flex-wrap items-center gap-2 text-sm"
        >
          <span class="text-fg-muted">{{ t('competitions.list.filters.status') }}</span>
          <UiBadge
            v-for="status in statusFilter"
            :key="status"
            tone="neutral"
          >
            {{ t(`competitions.status.${status}`) }}
          </UiBadge>
          <UiButton
            variant="ghost"
            size="sm"
            :icon="X"
            @click="setQuery({ status: null })"
          >
            {{ t('competitions.list.filters.clear_status') }}
          </UiButton>
        </div>

        <UiCard
          v-if="loadError && items.length === 0"
          padding="none"
        >
          <UiErrorState
            :error="loadError"
            :retrying="loading"
            @retry="load"
          />
        </UiCard>
        <UiTable
          v-else
          :columns="columns"
          :rows="items"
          row-key="id"
          :caption="t('competitions.list.caption')"
          :loading="loading && items.length === 0"
          stack-below="xl"
        >
          <template #cell-title="{ row }">
            <span class="flex min-w-0 flex-col gap-0.5">
              <NuxtLinkLocale
                :to="linkFor(asItem(row))"
                class="line-clamp-2 font-semibold text-fg hover:text-link hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                :title="asItem(row).title"
              >
                {{ asItem(row).title }}
              </NuxtLinkLocale>
              <span class="flex flex-wrap items-center gap-x-2 text-xs text-fg-muted">
                <bdi
                  v-if="asItem(row).reference_no"
                  class="tabular-nums"
                >{{ asItem(row).reference_no }}</bdi>
                <span>{{ asItem(row).category.name }}</span>
              </span>
            </span>
          </template>
          <template #cell-direction="{ row }">
            <CompetitionsDirectionChip
              :direction="asItem(row).direction"
              :with-rule="false"
              size="sm"
            />
          </template>
          <template #cell-status="{ row }">
            <CompetitionsStatusChip
              :status="asItem(row).status"
              :phase="asItem(row).phase"
              :effective-close-at="asItem(row).schedule.effective_close_at"
              size="sm"
            />
          </template>
          <template #cell-timing="{ row }">
            <span
              v-if="asItem(row).status === 'live' && asItem(row).schedule.effective_close_at"
              class="flex flex-col text-sm"
            >
              <span class="text-xs text-fg-muted">{{ t('competitions.detail.countdown.closes') }}</span>
              <UiCountdown
                :ends-at="asItem(row).schedule.effective_close_at"
                :label="t('competitions.detail.countdown.closes')"
                :ended-label="t('competitions.detail.countdown.closing')"
                size="sm"
              />
            </span>
            <span
              v-else-if="asItem(row).status === 'scheduled' && asItem(row).schedule.bidding_opens_at"
              class="flex flex-col text-sm"
            >
              <span class="text-xs text-fg-muted">{{ t('competitions.list.opens') }}</span>
              <UiDateTime :value="asItem(row).schedule.bidding_opens_at" />
            </span>
            <span
              v-else-if="asItem(row).schedule.effective_close_at"
              class="flex flex-col text-sm"
            >
              <span class="text-xs text-fg-muted">{{ asItem(row).status === 'draft' ? t('competitions.list.planned_close') : t('competitions.list.closed') }}</span>
              <UiDateTime :value="asItem(row).schedule.effective_close_at" />
            </span>
            <span v-else>—</span>
          </template>
          <template #cell-counts="{ row }">
            <bdi
              class="tabular-nums"
              :title="t('competitions.list.counts_title')"
            >{{ asItem(row).counts.invitations }} · {{ asItem(row).counts.joined }} · {{ asItem(row).counts.participants_with_offers }}</bdi>
          </template>
          <template #cell-leading="{ row }">
            <UiAmount :minor="asItem(row).leading_amount_minor" />
          </template>
          <template #cell-updated="{ row }">
            <UiRelativeTime :value="asItem(row).updated_at" />
          </template>
          <template #empty>
            <UiEmptyState
              :title="t(`competitions.list.empty.${emptyKey}.title`)"
              :description="t(`competitions.list.empty.${emptyKey}.body`)"
              :icon="ClipboardList"
            >
              <UiButton
                v-if="createEnabled && emptyKey !== 'filtered'"
                :to="CREATE_COMPETITION_ACTION.to"
                :icon="Plus"
              >
                {{ t('nav.create_competition') }}
              </UiButton>
            </UiEmptyState>
          </template>
        </UiTable>

        <p class="text-xs text-fg-muted">
          {{ t('competitions.list.counts_legend') }} · {{ t('common.prices_exclude_vat') }}
        </p>

        <UiPagination
          v-if="pageCount > 1"
          v-model:page="pageModel"
          :page-count="pageCount"
        />
      </div>
    </UiTabs>
  </div>
</template>
