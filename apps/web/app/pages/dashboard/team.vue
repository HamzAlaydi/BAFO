<script setup lang="ts">
import { Check, EllipsisVertical, Lock, Mail, Pencil, Power, PowerOff, Trash2, UserPlus, Users } from '@lucide/vue'
import { fetchTeamMembers, removeTeamMember, resendTeamInvitation, updateTeamMember } from '~/services/identity'
import type { Membership, MembershipStatus, TeamSeats } from '~/types/api/identity'
import type { MenuEntry, TableColumn } from '~/types/ui'

/**
 * W26 Team · `/dashboard/team` (`team.manage`; SCREENS §2.4). Seats meter, members with role, flags,
 * status and dates; invite, edit role and flags, deactivate or reactivate, remove, resend the
 * invitation. The owner's row and the viewer's own row are locked. No optimistic state changes.
 */
definePageMeta({ layout: 'dashboard', middleware: 'auth' })

const { t } = useI18n()
const auth = useAuthStore()
const toast = useToast()
const { message } = useErrorMessage()

useSeoMeta({ title: () => t('team.title') })

type StatusFilter = 'all' | MembershipStatus
const statusFilter = ref<StatusFilter>('all')
const members = ref<Membership[]>([])
const seats = ref<TeamSeats | null>(null)
const loading = ref(true)
const loadError = ref<unknown>(null)
const drawerOpen = ref(false)
const editing = ref<Membership | null>(null)
const seatLimit = ref<TeamSeats | null>(null)
const busyId = ref<string | null>(null)
const removeTarget = ref<Membership | null>(null)
const removeOpen = ref(false)
const removing = ref(false)
const removeError = ref<string | null>(null)

const allowed = computed(() => auth.can('team.manage'))

let seq = 0
async function load(): Promise<void> {
  if (!allowed.value) return
  const current = ++seq
  loading.value = true
  loadError.value = null
  try {
    const result = await fetchTeamMembers(statusFilter.value === 'all' ? undefined : statusFilter.value)
    if (current !== seq) return
    members.value = result.members
    seats.value = result.seats
  }
  catch (error) {
    if (current === seq) loadError.value = error
  }
  finally {
    if (current === seq) loading.value = false
  }
}

onMounted(load)
watch(statusFilter, () => void load())

const filterOptions = computed(() => (['all', 'active', 'invited', 'inactive'] as const).map(value => ({
  value,
  label: t(`team.filters.${value}`),
})))

const columns = computed<TableColumn[]>(() => [
  { key: 'name', label: t('team.fields.name'), primary: true },
  { key: 'role', label: t('team.fields.role') },
  { key: 'status', label: t('team.fields.status') },
  { key: 'date', label: t('team.fields.date') },
  { key: 'actions', label: t('team.fields.actions'), align: 'end' },
])

const isLocked = (member: Membership) => member.role === 'owner' || member.user.id === auth.user?.id
// The owner is always treated as able to award and purchase (ARCHITECTURE §5.3).
const hasFlag = (member: Membership, flag: 'can_award' | 'can_purchase') => member.role === 'owner' || member[flag]

const STATUS_TONES = { active: 'primary', invited: 'info', inactive: 'neutral' } as const

function rowMenu(member: Membership): MenuEntry[] {
  const entries: MenuEntry[] = [{ key: 'edit', label: t('team.actions.edit'), icon: Pencil }]
  if (member.status === 'invited') entries.push({ key: 'resend', label: t('team.actions.resend'), icon: Mail })
  if (member.status === 'active') entries.push({ key: 'deactivate', label: t('team.actions.deactivate'), icon: PowerOff })
  if (member.status === 'inactive') entries.push({ key: 'reactivate', label: t('team.actions.reactivate'), icon: Power })
  entries.push({ type: 'separator', key: 'sep' }, { key: 'remove', label: t('team.actions.remove'), icon: Trash2, danger: true })
  return entries
}

function openInvite(): void {
  editing.value = null
  seatLimit.value = null
  drawerOpen.value = true
}

function onRowAction(member: Membership, key: string): void {
  if (key === 'edit') {
    editing.value = member
    drawerOpen.value = true
  }
  else if (key === 'resend') void resend(member)
  else if (key === 'deactivate') void setStatus(member, 'inactive')
  else if (key === 'reactivate') void setStatus(member, 'active')
  else if (key === 'remove') {
    removeTarget.value = member
    removeError.value = null
    removeOpen.value = true
  }
}

function upsert(member: Membership): void {
  const exists = members.value.some(item => item.id === member.id)
  members.value = exists ? members.value.map(item => (item.id === member.id ? member : item)) : [...members.value, member]
}

function showBusinessError(error: unknown): void {
  if (error instanceof ApiError && error.code === 'seat_limit_reached') {
    seatLimit.value = (error.details.seats as TeamSeats | undefined) ?? seats.value
    return
  }
  toast.error(message(error))
}

async function onSaved(member: Membership, created: boolean): Promise<void> {
  upsert(member)
  toast.success(created ? t('team.toasts.invited', { email: member.user.email }) : t('team.toasts.updated'))
  seatLimit.value = null
  if (created) await load()
}

async function setStatus(member: Membership, status: 'active' | 'inactive'): Promise<void> {
  busyId.value = member.id
  try {
    upsert(await updateTeamMember(member.id, { status }))
    toast.success(status === 'active' ? t('team.toasts.reactivated') : t('team.toasts.deactivated'))
    await load()
  }
  catch (error) {
    showBusinessError(error)
  }
  finally {
    busyId.value = null
  }
}

async function resend(member: Membership): Promise<void> {
  busyId.value = member.id
  try {
    await resendTeamInvitation(member.id)
    toast.success(t('team.toasts.resent', { email: member.user.email }))
  }
  catch (error) {
    showBusinessError(error)
  }
  finally {
    busyId.value = null
  }
}

async function confirmRemove(): Promise<void> {
  const target = removeTarget.value
  if (!target) return
  removing.value = true
  removeError.value = null
  try {
    await removeTeamMember(target.id)
    members.value = members.value.filter(item => item.id !== target.id)
    removeOpen.value = false
    toast.success(t('team.toasts.removed', { name: target.user.name }))
    await load()
  }
  catch (error) {
    removeError.value = message(error)
  }
  finally {
    removing.value = false
  }
}
</script>

<template>
  <div class="flex flex-col gap-6">
    <UiPageHeader
      :title="t('team.title')"
      :description="t('team.subtitle')"
    >
      <template
        v-if="allowed"
        #actions
      >
        <UiButton
          :icon="UserPlus"
          @click="openInvite"
        >
          {{ t('team.actions.invite') }}
        </UiButton>
      </template>
    </UiPageHeader>

    <UiForbiddenState v-if="!allowed" />

    <template v-else>
      <UiAlert
        v-if="seatLimit"
        tone="warning"
        :title="t('team.seat_limit.title')"
        dismissible
        @dismiss="seatLimit = null"
      >
        <p>{{ t('errors.seat_limit_reached_detail', { used: seatLimit.used, total: seatLimit.total }) }}</p>
        <UiButton
          v-if="auth.can('billing.purchase')"
          class="mt-3"
          size="sm"
          variant="secondary"
          to="/dashboard/billing/plans"
        >
          {{ t('team.seat_limit.upgrade') }}
        </UiButton>
      </UiAlert>

      <UiCard v-if="seats">
        <TeamSeatsMeter :seats="seats" />
      </UiCard>

      <UiSegmented
        v-model="statusFilter"
        :options="filterOptions"
        :label="t('team.filters.label')"
        size="sm"
      />

      <UiCard
        v-if="loadError && members.length === 0"
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
        :rows="members"
        row-key="id"
        :caption="t('team.table_caption')"
        :loading="loading && members.length === 0"
      >
        <template #cell-name="{ row }">
          <span class="flex min-w-0 items-center gap-3">
            <UiAvatar
              :name="(row as Membership).user.name"
              :src="(row as Membership).user.avatar_url"
              size="sm"
            />
            <span class="flex min-w-0 flex-col">
              <span class="truncate font-semibold text-fg">
                {{ (row as Membership).user.name }}
                <span
                  v-if="(row as Membership).user.id === auth.user?.id"
                  class="text-xs font-normal text-fg-muted"
                >({{ t('team.you') }})</span>
              </span>
              <bdi class="truncate text-xs text-fg-muted">{{ (row as Membership).user.email }}</bdi>
            </span>
          </span>
        </template>
        <template #cell-role="{ row }">
          <span class="flex flex-col items-start gap-1">
            <span>{{ t(`team.roles.${(row as Membership).role}`) }}</span>
            <span
              v-if="hasFlag(row as Membership, 'can_award') || hasFlag(row as Membership, 'can_purchase')"
              class="flex flex-wrap gap-1"
            >
              <UiBadge
                v-if="hasFlag(row as Membership, 'can_award')"
                tone="neutral"
                size="sm"
                :icon="Check"
              >{{ t('team.fields.can_award_short') }}</UiBadge>
              <UiBadge
                v-if="hasFlag(row as Membership, 'can_purchase')"
                tone="neutral"
                size="sm"
                :icon="Check"
              >{{ t('team.fields.can_purchase_short') }}</UiBadge>
            </span>
          </span>
        </template>
        <template #cell-status="{ row }">
          <UiBadge
            :tone="STATUS_TONES[(row as Membership).status]"
            size="sm"
            dot
          >
            {{ t(`team.statuses.${(row as Membership).status}`) }}
          </UiBadge>
        </template>
        <template #cell-date="{ row }">
          <span class="text-sm text-fg-muted">
            <template v-if="(row as Membership).joined_at">
              {{ t('team.joined_on') }} <UiDateTime
                :value="(row as Membership).joined_at"
                format="date"
              />
            </template>
            <template v-else-if="(row as Membership).invited_at">
              {{ t('team.invited_on') }} <UiDateTime
                :value="(row as Membership).invited_at"
                format="date"
              />
            </template>
            <template v-else>—</template>
          </span>
        </template>
        <template #cell-actions="{ row }">
          <span
            v-if="isLocked(row as Membership)"
            class="inline-flex items-center gap-1 text-xs text-fg-muted"
          >
            <Lock
              :size="14"
              aria-hidden="true"
            />
            {{ (row as Membership).role === 'owner' ? t('team.locked.owner') : t('team.locked.self') }}
          </span>
          <UiDropdownMenu
            v-else
            :label="t('team.row_actions', { name: (row as Membership).user.name })"
            :items="rowMenu(row as Membership)"
            trigger-class="size-9 justify-center text-fg-muted hover:bg-surface-muted hover:text-fg"
            @select="onRowAction(row as Membership, $event)"
          >
            <template #trigger>
              <UiSkeleton
                v-if="busyId === (row as Membership).id"
                shape="circle"
                class="size-4"
              />
              <EllipsisVertical
                v-else
                :size="18"
                aria-hidden="true"
              />
              <span class="sr-only">{{ t('team.row_actions', { name: (row as Membership).user.name }) }}</span>
            </template>
          </UiDropdownMenu>
        </template>
        <template #empty>
          <UiEmptyState
            :icon="Users"
            :title="t('team.list.empty.title')"
            :description="t('team.list.empty.body')"
          >
            <UiButton
              :icon="UserPlus"
              @click="openInvite"
            >
              {{ t('team.actions.invite') }}
            </UiButton>
          </UiEmptyState>
        </template>
      </UiTable>
    </template>

    <TeamMemberDrawer
      v-model:open="drawerOpen"
      :member="editing"
      @saved="onSaved"
      @seat-limit="seatLimit = $event ?? seats"
    />

    <UiConfirmDialog
      v-model:open="removeOpen"
      :title="t('team.remove.title')"
      :description="removeTarget ? t('team.remove.description', { name: removeTarget.user.name }) : undefined"
      :confirm-label="t('team.remove.confirm')"
      :busy="removing"
      :error="removeError"
      danger
      @confirm="confirmRemove"
    />
  </div>
</template>
