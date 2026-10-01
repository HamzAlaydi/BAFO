<script setup lang="ts">
import { BadgeCheck, Building2, Contact, Mail, Plus, Trash2 } from '@lucide/vue'
import { fetchSuggestions } from '~/services/competitions'
import { listVendors } from '~/services/integrations'
import type { Invitation, Suggestion } from '~/types/api/competitions'
import type { Vendor } from '~/types/api/integrations'
import type { TabItem } from '~/types/ui'
import {
  addStaged,
  isValidStagedEmail,
  MAX_INVITATIONS_PER_REQUEST,
  stageEmail,
  stagedKey,
  stageOrganization,
  stageVendor,
  type StagedInvitation,
} from '~/stores/competition-editor-invitations'

/**
 * Invite picker (SCREENS W15 step 6 and the W17 invite drawer): Suggestions (`GET …/suggestions`,
 * match chips), E-mail (pasted list as chips) and Vendors (`GET /vendors`; blocked vendors disabled).
 * Picks are staged below with an optional name and, in `selected` fee mode, a "cover fees" switch.
 * The parent sends the staged rows (all-or-nothing) and puts the per-row server errors back on them.
 */
const props = withDefaults(defineProps<{
  competitionId: string
  /** Current invitations, for duplicate detection. */
  existing?: Invitation[]
  /** `selected` sponsorship mode: each row gets a "cover fees" switch. */
  sponsoredSelectable?: boolean
  categoryId?: string | null
  regionId?: string | null
}>(), {
  existing: () => [],
  sponsoredSelectable: false,
  categoryId: null,
  regionId: null,
})

const staged = defineModel<StagedInvitation[]>({ default: () => [] })

const { t } = useI18n()
const locale = useAppLocale()
const lookups = useLookupsStore()
const joinList = (items: string[]) => items.join(locale.value === 'ar' ? '، ' : ', ')
const tab = ref('suggestions')
const notice = ref<string | null>(null)

const tabs = computed<TabItem[]>(() => [
  { key: 'suggestions', label: t('invitations.issuer.picker.tabs.suggestions'), icon: Building2 },
  { key: 'email', label: t('invitations.issuer.picker.tabs.email'), icon: Mail },
  { key: 'vendors', label: t('invitations.issuer.picker.tabs.vendors'), icon: Contact },
])

const invitedKeys = computed(() => {
  const keys = new Set<string>()
  for (const invitation of props.existing) {
    if (invitation.status === 'revoked') continue
    keys.add(stagedKey('email', invitation.email))
    if (invitation.organization) keys.add(stagedKey('organization', invitation.organization.id))
    if (invitation.vendor) keys.add(stagedKey('vendor', invitation.vendor.id))
  }
  return keys
})
const stagedKeys = computed(() => new Set(staged.value.map(row => row.key)))
const room = computed(() => MAX_INVITATIONS_PER_REQUEST - staged.value.length)

function add(rows: StagedInvitation[]): void {
  const capped = rows.slice(0, Math.max(0, room.value))
  const result = addStaged(staged.value, capped, props.existing)
  staged.value = result.rows
  const skipped = result.duplicates
  notice.value = skipped > 0 ? t('invitations.issuer.picker.duplicates_skipped', { count: skipped }, skipped) : null
  if (rows.length > capped.length) notice.value = t('invitations.issuer.picker.max_rows', { max: MAX_INVITATIONS_PER_REQUEST })
}

function isTaken(key: string): boolean {
  return stagedKeys.value.has(key) || invitedKeys.value.has(key)
}

// ---------- Suggestions ----------

const suggestionQuery = ref('')
const suggestionCategory = ref<string | null>(props.categoryId)
const suggestionRegion = ref<string | null>(props.regionId)
const suggestions = ref<Suggestion[]>([])
const suggestionsLoading = ref(false)
const suggestionsError = ref<unknown>(null)
let suggestionSeq = 0

async function loadSuggestions(): Promise<void> {
  const current = ++suggestionSeq
  suggestionsLoading.value = true
  suggestionsError.value = null
  try {
    const list = await fetchSuggestions(props.competitionId, {
      q: suggestionQuery.value || undefined,
      category_id: suggestionCategory.value ?? undefined,
      region_id: suggestionRegion.value ?? undefined,
      limit: 20,
    })
    if (current === suggestionSeq) suggestions.value = list
  }
  catch (error) {
    if (current === suggestionSeq) suggestionsError.value = error
  }
  finally {
    if (current === suggestionSeq) suggestionsLoading.value = false
  }
}

watch([suggestionQuery, suggestionCategory, suggestionRegion], () => void loadSuggestions())
onMounted(() => {
  void lookups.ensureLoaded().catch(() => {})
  void loadSuggestions()
})

const categoryOptions = computed(() => lookups.categories.map(category => ({ value: category.id, label: category.name })))
const regionOptions = computed(() => lookups.regions.map(region => ({ value: region.id, label: region.name })))

// ---------- E-mail ----------

const emails = ref<string[]>([])
const validEmails = computed(() => emails.value.filter(email => isEmail(email)))

function addEmails(): void {
  add(validEmails.value.map(email => stageEmail(email)))
  emails.value = emails.value.filter(email => !isEmail(email))
}

// ---------- Vendors ----------

const vendorQuery = ref('')
const vendors = ref<Vendor[]>([])
const vendorsLoading = ref(false)
const vendorsError = ref<unknown>(null)
const vendorsLoaded = ref(false)
let vendorSeq = 0

async function loadVendors(): Promise<void> {
  const current = ++vendorSeq
  vendorsLoading.value = true
  vendorsError.value = null
  try {
    const page = await listVendors({ q: vendorQuery.value || undefined, per_page: 20 })
    if (current === vendorSeq) vendors.value = page.items.filter(vendor => vendor.status !== 'archived')
  }
  catch (error) {
    if (current === vendorSeq) vendorsError.value = error
  }
  finally {
    if (current === vendorSeq) {
      vendorsLoading.value = false
      vendorsLoaded.value = true
    }
  }
}

watch(vendorQuery, () => void loadVendors())
watch(tab, (value) => {
  if (value === 'vendors' && !vendorsLoaded.value) void loadVendors()
})

// ---------- Staged rows ----------

function patchRow(key: string, patch: Partial<StagedInvitation>): void {
  staged.value = staged.value.map(row => (row.key === key ? { ...row, ...patch, error: null, errorCode: null } : row))
}

function removeRow(key: string): void {
  staged.value = staged.value.filter(row => row.key !== key)
}

function rowError(row: StagedInvitation): string | null {
  if (row.errorCode) return t(`invitations.issuer.item_codes.${row.errorCode}`)
  if (row.error) return row.error
  return isValidStagedEmail(row) ? null : t('validation.email')
}

const KIND_ICONS = { email: Mail, organization: Building2, vendor: Contact } as const
</script>

<template>
  <div class="flex flex-col gap-5">
    <UiTabs
      v-model="tab"
      :items="tabs"
      :label="t('invitations.issuer.picker.label')"
    >
      <template #suggestions>
        <div class="flex flex-col gap-4">
          <div class="grid gap-3 md:grid-cols-3">
            <UiSearchInput
              v-model="suggestionQuery"
              :label="t('invitations.issuer.picker.search_organizations')"
              :placeholder="t('invitations.issuer.picker.search_organizations')"
            />
            <UiSelect
              v-model="suggestionCategory"
              :options="categoryOptions"
              :label="t('invitations.issuer.picker.category')"
              :placeholder="t('common.select_placeholder')"
            />
            <UiSelect
              v-model="suggestionRegion"
              :options="regionOptions"
              :label="t('invitations.issuer.picker.region')"
              :placeholder="t('common.select_placeholder')"
            />
          </div>
          <div
            v-if="suggestionsLoading && suggestions.length === 0"
            class="flex flex-col gap-2"
          >
            <UiSkeleton class="h-14 w-full" />
            <UiSkeleton class="h-14 w-full" />
          </div>
          <UiErrorState
            v-else-if="suggestionsError"
            :error="suggestionsError"
            compact
            @retry="loadSuggestions"
          />
          <UiEmptyState
            v-else-if="suggestions.length === 0"
            :title="t('invitations.issuer.picker.no_suggestions')"
            :description="t('invitations.issuer.picker.no_suggestions_body')"
            :icon="Building2"
            compact
          />
          <ul
            v-else
            class="divide-y divide-line rounded-md border border-line"
          >
            <li
              v-for="suggestion in suggestions"
              :key="suggestion.organization.id"
              class="flex flex-wrap items-center gap-3 p-3"
            >
              <UiOrgLogo
                :name="suggestion.organization.name"
                :src="suggestion.organization.logo_url"
                size="sm"
              />
              <div class="flex min-w-0 flex-1 flex-col gap-1">
                <span class="flex items-center gap-1.5 font-semibold text-fg">
                  <span class="truncate">{{ suggestion.organization.name }}</span>
                  <BadgeCheck
                    v-if="suggestion.organization.verified"
                    :size="16"
                    class="shrink-0 text-brand"
                    :aria-label="t('invitations.issuer.picker.verified')"
                  />
                </span>
                <span class="flex flex-wrap gap-1.5">
                  <UiBadge
                    v-if="suggestion.match.category"
                    size="sm"
                    tone="primary"
                  >
                    {{ t('invitations.issuer.picker.match_category') }}
                  </UiBadge>
                  <UiBadge
                    v-if="suggestion.match.region"
                    size="sm"
                    tone="primary"
                  >
                    {{ t('invitations.issuer.picker.match_region') }}
                  </UiBadge>
                  <UiBadge
                    v-if="suggestion.has_active_plan"
                    size="sm"
                    tone="info"
                  >
                    {{ t('invitations.issuer.picker.has_plan') }}
                  </UiBadge>
                  <span
                    v-if="suggestion.region"
                    class="text-xs text-fg-muted"
                  >{{ suggestion.region.name }}</span>
                  <span
                    v-if="suggestion.categories.length"
                    class="text-xs text-fg-muted"
                  >· {{ joinList(suggestion.categories.slice(0, 2).map(category => category.name)) }}</span>
                </span>
              </div>
              <UiButton
                size="sm"
                variant="secondary"
                :icon="Plus"
                :disabled="isTaken(stagedKey('organization', suggestion.organization.id)) || room <= 0"
                @click="add([stageOrganization(suggestion.organization.id, suggestion.organization.name)])"
              >
                {{ isTaken(stagedKey('organization', suggestion.organization.id)) ? t('invitations.issuer.picker.added') : t('invitations.issuer.picker.add') }}
              </UiButton>
            </li>
          </ul>
        </div>
      </template>

      <template #email>
        <div class="flex flex-col gap-3">
          <UiEmailChipsInput
            v-model="emails"
            :label="t('invitations.issuer.picker.emails_label')"
            :hint="t('invitations.issuer.picker.emails_hint')"
          />
          <div class="flex justify-end">
            <UiButton
              variant="secondary"
              :icon="Plus"
              :disabled="validEmails.length === 0 || room <= 0"
              @click="addEmails"
            >
              {{ t('invitations.issuer.picker.add_emails', { count: validEmails.length }, validEmails.length) }}
            </UiButton>
          </div>
        </div>
      </template>

      <template #vendors>
        <div class="flex flex-col gap-4">
          <UiSearchInput
            v-model="vendorQuery"
            :label="t('invitations.issuer.picker.search_vendors')"
            :placeholder="t('invitations.issuer.picker.search_vendors')"
          />
          <div
            v-if="vendorsLoading && vendors.length === 0"
            class="flex flex-col gap-2"
          >
            <UiSkeleton class="h-12 w-full" />
            <UiSkeleton class="h-12 w-full" />
          </div>
          <UiErrorState
            v-else-if="vendorsError"
            :error="vendorsError"
            compact
            @retry="loadVendors"
          />
          <UiEmptyState
            v-else-if="vendors.length === 0"
            :title="t('invitations.issuer.picker.no_vendors')"
            :icon="Contact"
            compact
          />
          <ul
            v-else
            class="divide-y divide-line rounded-md border border-line"
          >
            <li
              v-for="vendor in vendors"
              :key="vendor.id"
              class="flex flex-wrap items-center gap-3 p-3"
            >
              <div class="flex min-w-0 flex-1 flex-col">
                <span class="truncate font-semibold text-fg">{{ vendor.name }}</span>
                <bdi class="truncate text-sm text-fg-muted">{{ vendor.email }}</bdi>
              </div>
              <UiBadge
                v-if="vendor.status === 'blocked'"
                tone="neutral"
                size="sm"
              >
                {{ t('invitations.issuer.picker.vendor_blocked') }}
              </UiBadge>
              <UiButton
                v-else
                size="sm"
                variant="secondary"
                :icon="Plus"
                :disabled="isTaken(stagedKey('vendor', vendor.id)) || isTaken(stagedKey('email', vendor.email)) || room <= 0"
                @click="add([stageVendor(vendor.id, vendor.name, vendor.email)])"
              >
                {{ isTaken(stagedKey('vendor', vendor.id)) ? t('invitations.issuer.picker.added') : t('invitations.issuer.picker.add') }}
              </UiButton>
            </li>
          </ul>
        </div>
      </template>
    </UiTabs>

    <p
      v-if="notice"
      class="text-sm text-fg-muted"
      role="status"
    >
      {{ notice }}
    </p>

    <section
      v-if="staged.length > 0"
      class="flex flex-col gap-3"
      :aria-label="t('invitations.issuer.picker.staged_title')"
    >
      <h3 class="text-sm font-bold text-fg">
        {{ t('invitations.issuer.picker.staged_title') }} <bdi class="tabular-nums">({{ staged.length }})</bdi>
      </h3>
      <ul class="flex flex-col gap-2">
        <li
          v-for="row in staged"
          :key="row.key"
          class="flex flex-col gap-2 rounded-md border p-3"
          :class="rowError(row) ? 'border-danger' : 'border-line'"
        >
          <div class="flex flex-wrap items-center gap-3">
            <component
              :is="KIND_ICONS[row.kind]"
              :size="18"
              class="shrink-0 text-fg-muted"
              aria-hidden="true"
            />
            <span class="min-w-0 flex-1 truncate font-semibold text-fg">
              <bdi>{{ row.label }}</bdi>
            </span>
            <UiIconButton
              :icon="Trash2"
              :label="t('invitations.issuer.picker.remove_row', { label: row.label })"
              size="sm"
              variant="danger-ghost"
              @click="removeRow(row.key)"
            />
          </div>
          <div class="grid items-end gap-3 sm:grid-cols-2">
            <UiInput
              :model-value="row.name"
              :label="t('invitations.issuer.picker.name_label')"
              :maxlength="150"
              @update:model-value="value => patchRow(row.key, { name: value })"
            />
            <UiSwitch
              v-if="sponsoredSelectable"
              :model-value="row.sponsored"
              :label="t('invitations.issuer.picker.cover_fees')"
              @update:model-value="value => patchRow(row.key, { sponsored: value })"
            />
          </div>
          <p
            v-if="rowError(row)"
            class="text-sm text-danger"
            role="alert"
          >
            {{ rowError(row) }}
          </p>
        </li>
      </ul>
    </section>
  </div>
</template>
