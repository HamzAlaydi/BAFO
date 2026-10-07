<script setup lang="ts">
import { Archive, BadgeCheck, Contact, EllipsisVertical, FileDown, FileUp, Pencil, Plus, RotateCcw, SearchX } from '@lucide/vue'
import { archiveVendor, fetchVendor, listVendors, updateVendor } from '~/services/integrations'
import type { PagePagination } from '~/types/api/common'
import type { Vendor, VendorStatus } from '~/types/api/integrations'
import type { MenuEntry, SelectOption, TableColumn } from '~/types/ui'

/**
 * W25 Vendors · `/dashboard/vendors` (`competitions.create`; SCREENS §2.4). The organization's vendor
 * directory: search, status, category and region filters; create and edit in `VendorDrawer`;
 * archive (confirm) and restore. Links to import (W41) and export (W42) with `integrations.manage`.
 */
definePageMeta({ layout: 'dashboard', middleware: ['auth', 'feature'], feature: 'vendor_directory' })

const { t } = useI18n()
const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const toast = useToast()
const locale = useAppLocale()
const lookups = useLookupsStore()
const { message } = useErrorMessage()

useSeoMeta({ title: () => t('vendors.title') })

const allowed = computed(() => auth.can('competitions.create'))
const canIntegrate = computed(() => auth.can('integrations.manage'))

type StatusFilter = 'all' | VendorStatus
const STATUSES: readonly StatusFilter[] = ['all', 'active', 'blocked', 'archived']
const initialStatus = queryString(route.query.status)

const q = ref(queryString(route.query.q) ?? '')
const statusFilter = ref<StatusFilter>(initialStatus && (STATUSES as readonly string[]).includes(initialStatus) ? initialStatus as StatusFilter : 'all')
const categoryId = ref<string | null>(queryString(route.query.category_id))
const regionId = ref<string | null>(queryString(route.query.region_id))
const page = ref(parsePositiveInt(route.query.page) ?? 1)

const vendors = ref<Vendor[]>([])
const pagination = ref<PagePagination | null>(null)
const loading = ref(true)
const loadError = ref<unknown>(null)
const busyId = ref<string | null>(null)
const drawerOpen = ref(false)
const editing = ref<Vendor | null>(null)
const archiveTarget = ref<Vendor | null>(null)
const archiveOpen = ref(false)
const archiving = ref(false)
const archiveError = ref<string | null>(null)

const filtered = computed(() => Boolean(q.value || statusFilter.value !== 'all' || categoryId.value || regionId.value))

let seq = 0
async function load(): Promise<void> {
  if (!allowed.value) return
  const current = ++seq
  loading.value = true
  loadError.value = null
  try {
    const result = await listVendors({
      q: q.value || undefined,
      status: statusFilter.value === 'all' ? undefined : statusFilter.value,
      category_id: categoryId.value ?? undefined,
      region_id: regionId.value ?? undefined,
      page: page.value,
    })
    if (current !== seq) return
    vendors.value = result.items
    pagination.value = result.pagination
  }
  catch (error) {
    if (current === seq) loadError.value = error
  }
  finally {
    if (current === seq) loading.value = false
  }
}

onMounted(() => {
  if (!allowed.value) return
  lookups.ensureLoaded().catch(() => {})
  void load()
})

function syncQuery(): void {
  void router.replace({
    query: {
      q: q.value || undefined,
      status: statusFilter.value === 'all' ? undefined : statusFilter.value,
      category_id: categoryId.value ?? undefined,
      region_id: regionId.value ?? undefined,
      page: page.value > 1 ? String(page.value) : undefined,
    },
  })
}

watch([q, statusFilter, categoryId, regionId], () => {
  if (page.value !== 1) {
    page.value = 1
    return
  }
  syncQuery()
  void load()
})
watch(page, () => {
  syncQuery()
  void load()
})

const pageCount = computed(() => pagination.value?.last_page ?? (pagination.value?.has_more ? page.value + 1 : page.value))

const statusOptions = computed<SelectOption<StatusFilter>[]>(() => STATUSES.map(value => ({
  value,
  label: value === 'all' ? t('vendors.filters.all_statuses') : t(`vendors.statuses.${value}`),
})))
/** `all` stands for "no filter" so the choice can be undone (the native placeholder is not selectable). */
const ALL = 'all'
const categoryOptions = computed<SelectOption<string>[]>(() => [
  { value: ALL, label: t('vendors.filters.all_categories') },
  ...lookups.categories.map(category => ({ value: category.id, label: category.name })),
])
const regionOptions = computed<SelectOption<string>[]>(() => [
  { value: ALL, label: t('vendors.filters.all_regions') },
  ...lookups.regions.map(region => ({ value: region.id, label: region.name })),
])
const statusModel = computed<StatusFilter | null>({ get: () => statusFilter.value, set: value => (statusFilter.value = value ?? 'all') })
const categoryModel = computed<string | null>({ get: () => categoryId.value ?? ALL, set: value => (categoryId.value = value && value !== ALL ? value : null) })
const regionModel = computed<string | null>({ get: () => regionId.value ?? ALL, set: value => (regionId.value = value && value !== ALL ? value : null) })

const columns = computed<TableColumn[]>(() => [
  { key: 'name', label: t('vendors.fields.name'), primary: true },
  { key: 'email', label: t('vendors.fields.email') },
  { key: 'cr_number', label: t('vendors.fields.cr_number'), hideOnMobile: true },
  { key: 'region', label: t('vendors.fields.region'), hideOnMobile: true },
  { key: 'categories', label: t('vendors.fields.categories'), hideOnMobile: true },
  { key: 'status', label: t('vendors.fields.status') },
  { key: 'source', label: t('vendors.fields.source'), hideOnMobile: true, hideBelow: 'xl' },
  { key: 'actions', label: t('vendors.fields.actions'), align: 'end' },
])

const row = (value: unknown) => value as Vendor

function rowMenu(vendor: Vendor): MenuEntry[] {
  const entries: MenuEntry[] = [{ key: 'edit', label: t('vendors.actions.edit'), icon: Pencil }]
  if (vendor.status === 'archived') entries.push({ key: 'restore', label: t('vendors.actions.restore'), icon: RotateCcw })
  else entries.push({ type: 'separator', key: 'sep' }, { key: 'archive', label: t('vendors.actions.archive'), icon: Archive, danger: true })
  return entries
}

function openCreate(): void {
  editing.value = null
  drawerOpen.value = true
}

function onRowAction(vendor: Vendor, key: string): void {
  if (key === 'edit') {
    editing.value = vendor
    drawerOpen.value = true
  }
  else if (key === 'restore') void restore(vendor)
  else if (key === 'archive') {
    archiveTarget.value = vendor
    archiveError.value = null
    archiveOpen.value = true
  }
}

function upsert(vendor: Vendor): void {
  const exists = vendors.value.some(item => item.id === vendor.id)
  vendors.value = exists ? vendors.value.map(item => (item.id === vendor.id ? vendor : item)) : [vendor, ...vendors.value]
}

function onSaved(vendor: Vendor, created: boolean): void {
  upsert(vendor)
  toast.success(created ? t('vendors.toasts.created', { name: vendorDisplayName(vendor, locale.value) }) : t('vendors.toasts.updated'))
  if (created) void load()
}

async function onOpenExisting(id: string): Promise<void> {
  try {
    editing.value = await fetchVendor(id)
    drawerOpen.value = true
  }
  catch (error) {
    toast.error(message(error))
  }
}

async function restore(vendor: Vendor): Promise<void> {
  busyId.value = vendor.id
  try {
    upsert(await updateVendor(vendor.id, { status: 'active' }))
    toast.success(t('vendors.toasts.restored', { name: vendorDisplayName(vendor, locale.value) }))
    void load()
  }
  catch (error) {
    toast.error(message(error))
  }
  finally {
    busyId.value = null
  }
}

async function confirmArchive(): Promise<void> {
  const target = archiveTarget.value
  if (!target) return
  archiving.value = true
  archiveError.value = null
  try {
    upsert(await archiveVendor(target.id))
    archiveOpen.value = false
    toast.success(t('vendors.toasts.archived', { name: vendorDisplayName(target, locale.value) }))
    void load()
  }
  catch (error) {
    archiveError.value = message(error)
  }
  finally {
    archiving.value = false
  }
}

function clearFilters(): void {
  q.value = ''
  statusFilter.value = 'all'
  categoryId.value = null
  regionId.value = null
}
</script>

<template>
  <div class="flex flex-col gap-6">
    <UiPageHeader
      :title="t('vendors.title')"
      :description="t('vendors.subtitle')"
    >
      <template
        v-if="allowed"
        #actions
      >
        <template v-if="canIntegrate">
          <UiButton
            variant="secondary"
            :icon="FileUp"
            to="/dashboard/integrations/import"
          >
            {{ t('vendors.actions.import') }}
          </UiButton>
          <UiButton
            variant="secondary"
            :icon="FileDown"
            :to="{ path: '/dashboard/integrations/exports', query: { type: 'vendors' } }"
          >
            {{ t('vendors.actions.export') }}
          </UiButton>
        </template>
        <UiButton
          :icon="Plus"
          @click="openCreate"
        >
          {{ t('vendors.actions.add') }}
        </UiButton>
      </template>
    </UiPageHeader>

    <UiForbiddenState v-if="!allowed" />

    <template v-else>
      <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(0,2fr)_repeat(3,minmax(0,1fr))] xl:items-end">
        <UiSearchInput
          v-model="q"
          :label="t('vendors.filters.search')"
          :placeholder="t('vendors.filters.search')"
          class="sm:col-span-2 xl:col-span-1"
        />
        <UiSelect
          v-model="statusModel"
          :options="statusOptions"
          :label="t('vendors.filters.status')"
        />
        <UiSelect
          v-model="categoryModel"
          :options="categoryOptions"
          :label="t('vendors.filters.category')"
        />
        <UiSelect
          v-model="regionModel"
          :options="regionOptions"
          :label="t('vendors.filters.region')"
        />
      </div>

      <UiCard
        v-if="loadError && vendors.length === 0"
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
        :rows="vendors"
        row-key="id"
        :caption="t('vendors.caption')"
        stack-below="lg"
        :loading="loading && vendors.length === 0"
      >
        <template #cell-name="{ row: r }">
          <span class="flex min-w-0 flex-col gap-0.5">
            <span class="flex flex-wrap items-center gap-2">
              <span class="font-semibold text-fg">{{ row(r).name }}</span>
              <UiBadge
                v-if="row(r).linked_organization"
                tone="info"
                size="sm"
                :icon="BadgeCheck"
              >
                {{ t('vendors.linked') }}
              </UiBadge>
            </span>
            <bdi
              v-if="row(r).name_en"
              class="text-xs text-fg-muted"
            >{{ row(r).name_en }}</bdi>
            <span
              v-if="row(r).external_refs.length > 0"
              class="text-xs text-fg-muted"
            >{{ t('vendors.refs_count', { count: row(r).external_refs.length }, row(r).external_refs.length) }}</span>
          </span>
        </template>
        <template #cell-email="{ row: r }">
          <bdi class="text-sm break-all">{{ row(r).email }}</bdi>
        </template>
        <template #cell-cr_number="{ row: r }">
          <bdi v-if="row(r).cr_number">{{ row(r).cr_number }}</bdi>
          <span v-else>—</span>
        </template>
        <template #cell-region="{ row: r }">
          {{ row(r).region?.name ?? '—' }}
        </template>
        <template #cell-categories="{ row: r }">
          <span
            v-if="row(r).categories.length > 0"
            class="text-sm"
          >
            {{ row(r).categories.slice(0, 2).map(category => category.name).join(t('vendors.list_separator')) }}
            <span
              v-if="row(r).categories.length > 2"
              class="text-fg-muted"
            >{{ t('vendors.categories_more', { count: row(r).categories.length - 2 }) }}</span>
          </span>
          <span v-else>—</span>
        </template>
        <template #cell-status="{ row: r }">
          <UiBadge
            :tone="VENDOR_STATUS_TONES[row(r).status]"
            size="sm"
            dot
          >
            {{ t(`vendors.statuses.${row(r).status}`) }}
          </UiBadge>
        </template>
        <template #cell-source="{ row: r }">
          {{ t(`vendors.sources.${row(r).source}`) }}
        </template>
        <template #cell-actions="{ row: r }">
          <UiDropdownMenu
            :label="t('vendors.row_actions', { name: row(r).name })"
            :items="rowMenu(row(r))"
            trigger-class="size-11 justify-center text-fg-muted hover:bg-surface-muted hover:text-fg"
            @select="onRowAction(row(r), $event)"
          >
            <template #trigger>
              <UiSkeleton
                v-if="busyId === row(r).id"
                shape="circle"
                class="size-4"
              />
              <EllipsisVertical
                v-else
                :size="18"
                aria-hidden="true"
              />
              <span class="sr-only">{{ t('vendors.row_actions', { name: row(r).name }) }}</span>
            </template>
          </UiDropdownMenu>
        </template>
        <template #empty>
          <UiEmptyState
            v-if="filtered"
            :icon="SearchX"
            :title="t('vendors.no_results.title')"
            :description="t('vendors.no_results.body')"
          >
            <UiButton
              variant="secondary"
              @click="clearFilters"
            >
              {{ t('vendors.filters.clear') }}
            </UiButton>
          </UiEmptyState>
          <UiEmptyState
            v-else
            :icon="Contact"
            :title="t('vendors.list.empty.title')"
            :description="t('vendors.list.empty.body')"
          >
            <UiButton
              :icon="Plus"
              @click="openCreate"
            >
              {{ t('vendors.actions.add') }}
            </UiButton>
            <UiButton
              v-if="canIntegrate"
              variant="secondary"
              :icon="FileUp"
              to="/dashboard/integrations/import"
            >
              {{ t('vendors.actions.import') }}
            </UiButton>
          </UiEmptyState>
        </template>
      </UiTable>

      <UiPagination
        v-if="pageCount > 1"
        v-model:page="page"
        :page-count="pageCount"
      />
    </template>

    <VendorsVendorDrawer
      v-model:open="drawerOpen"
      :vendor="editing"
      @saved="onSaved"
      @open-existing="onOpenExisting"
    />

    <UiConfirmDialog
      v-model:open="archiveOpen"
      :title="t('vendors.archive.title')"
      :description="archiveTarget ? t('vendors.archive.description', { name: archiveTarget.name }) : undefined"
      :confirm-label="t('vendors.archive.confirm')"
      :busy="archiving"
      :error="archiveError"
      danger
      @confirm="confirmArchive"
    />
  </div>
</template>
