<script setup lang="ts">
import { EllipsisVertical, Mail, Pencil, Trash2, UserX, Users } from '@lucide/vue'
import { removeInvitation, resendInvitation, updateInvitation } from '~/services/competitions'
import type { CompetitionStatus, Invitation } from '~/types/api/competitions'
import type { SponsorshipMode } from '~/types/api/billing'
import type { MenuEntry, TableColumn } from '~/types/ui'

/**
 * The issuer's invitation table (SCREENS W15 step 6, W17): invitee (e-mail, organisation with the
 * verified badge, or vendor), name, status, alias once joined, coverage and pass status, dates, and
 * the row actions the state allows: resend (sent or viewed, before the cutoff; 3 per day), revoke
 * (sent or viewed, confirmed), remove and edit (draft). Rows change only after the server answers.
 */
const props = withDefaults(defineProps<{
  competitionId: string
  invitations: Invitation[]
  status: CompetitionStatus
  invitationCutoffAt?: string | null
  canManage: boolean
  sponsorshipMode?: SponsorshipMode
  loading?: boolean
  emptyTitle?: string
  emptyBody?: string
}>(), {
  invitationCutoffAt: null,
  sponsorshipMode: 'none',
  loading: false,
  emptyTitle: undefined,
  emptyBody: undefined,
})

const emit = defineEmits<{ updated: [invitation: Invitation], removed: [id: string] }>()

const { t } = useI18n()
const toast = useToast()
const { message } = useErrorMessage()
const clock = useServerTime()

const busyId = ref<string | null>(null)
const revokeTarget = ref<Invitation | null>(null)
const revokeOpen = ref(false)
const revoking = ref(false)
const revokeError = ref<string | null>(null)
const editTarget = ref<Invitation | null>(null)
const editOpen = ref(false)
const editName = ref('')
const editSponsored = ref(false)
const editing = ref(false)
const editError = ref<string | null>(null)

const showCoverage = computed(() => props.sponsorshipMode !== 'none' || props.invitations.some(item => item.coverage === 'sponsored'))
const beforeCutoff = computed(() => {
  if (!props.invitationCutoffAt) return true
  return Date.parse(props.invitationCutoffAt) > clock.now()
})

const columns = computed<TableColumn[]>(() => {
  const list: TableColumn[] = [
    { key: 'invitee', label: t('invitations.issuer.table.invitee'), primary: true },
    { key: 'name', label: t('invitations.issuer.table.name') },
    { key: 'status', label: t('invitations.issuer.table.status') },
  ]
  if (props.status !== 'draft') list.push({ key: 'alias', label: t('invitations.issuer.table.alias') })
  if (showCoverage.value) list.push({ key: 'coverage', label: t('invitations.issuer.table.coverage') })
  list.push({ key: 'date', label: t('invitations.issuer.table.date') })
  if (props.canManage) list.push({ key: 'actions', label: t('invitations.issuer.table.actions'), align: 'end' })
  return list
})

function lastEvent(invitation: Invitation): { key: string, at: string } | null {
  const events: Array<[string, string | null]> = [
    ['joined', invitation.joined_at],
    ['declined', invitation.declined_at],
    ['revoked', invitation.revoked_at],
    ['expired', invitation.expired_at],
    ['viewed', invitation.viewed_at],
    ['sent', invitation.sent_at],
    ['created', invitation.created_at],
  ]
  const found = events.find(([, at]) => Boolean(at))
  return found ? { key: found[0], at: found[1] as string } : null
}

function menu(invitation: Invitation): MenuEntry[] {
  const entries: MenuEntry[] = []
  const open = invitation.status === 'sent' || invitation.status === 'viewed'
  if (invitation.status === 'draft') {
    entries.push({ key: 'edit', label: t('invitations.issuer.actions.edit'), icon: Pencil })
    entries.push({ key: 'remove', label: t('invitations.issuer.actions.remove'), icon: Trash2, danger: true })
  }
  if (open && beforeCutoff.value) entries.push({ key: 'resend', label: t('invitations.issuer.actions.resend'), icon: Mail })
  if (open) entries.push({ key: 'revoke', label: t('invitations.issuer.actions.revoke'), icon: UserX, danger: true })
  return entries
}

async function onAction(invitation: Invitation, key: string): Promise<void> {
  if (key === 'edit') {
    editTarget.value = invitation
    editName.value = invitation.name ?? ''
    editSponsored.value = invitation.sponsored_requested
    editError.value = null
    editOpen.value = true
  }
  else if (key === 'revoke') {
    revokeTarget.value = invitation
    revokeError.value = null
    revokeOpen.value = true
  }
  else if (key === 'remove') {
    await remove(invitation)
  }
  else if (key === 'resend') {
    await resend(invitation)
  }
}

async function resend(invitation: Invitation): Promise<void> {
  busyId.value = invitation.id
  try {
    await resendInvitation(props.competitionId, invitation.id)
    toast.success(t('invitations.issuer.toasts.resent', { email: invitation.email }))
  }
  catch (error) {
    toast.error(error instanceof ApiError && error.code === 'too_many_requests' ? t('invitations.issuer.resend_limit') : message(error))
  }
  finally {
    busyId.value = null
  }
}

async function remove(invitation: Invitation): Promise<void> {
  busyId.value = invitation.id
  try {
    const result = await removeInvitation(props.competitionId, invitation.id)
    if (result) emit('updated', result)
    else emit('removed', invitation.id)
    toast.success(t('invitations.issuer.toasts.removed', { email: invitation.email }))
  }
  catch (error) {
    toast.error(message(error))
  }
  finally {
    busyId.value = null
  }
}

async function confirmRevoke(): Promise<void> {
  const target = revokeTarget.value
  if (!target) return
  revoking.value = true
  revokeError.value = null
  try {
    const result = await removeInvitation(props.competitionId, target.id)
    if (result) emit('updated', result)
    else emit('removed', target.id)
    revokeOpen.value = false
    toast.success(t('invitations.issuer.toasts.revoked', { email: target.email }))
  }
  catch (error) {
    revokeError.value = message(error)
  }
  finally {
    revoking.value = false
  }
}

async function saveEdit(): Promise<void> {
  const target = editTarget.value
  if (!target) return
  editing.value = true
  editError.value = null
  try {
    const body: { name: string | null, sponsored?: boolean } = { name: editName.value.trim() || null }
    if (props.sponsorshipMode === 'selected') body.sponsored = editSponsored.value
    emit('updated', await updateInvitation(props.competitionId, target.id, body))
    editOpen.value = false
    toast.success(t('invitations.issuer.toasts.updated'))
  }
  catch (error) {
    editError.value = message(error)
  }
  finally {
    editing.value = false
  }
}

const asInvitation = (row: unknown) => row as Invitation
</script>

<template>
  <div>
    <UiTable
      :columns="columns"
      :rows="invitations"
      row-key="id"
      :caption="t('invitations.issuer.table.caption')"
      stack-below="xl"
      :loading="loading && invitations.length === 0"
    >
      <template #cell-invitee="{ row }">
        <span class="flex min-w-0 flex-col">
          <bdi class="truncate font-semibold text-fg">{{ asInvitation(row).email }}</bdi>
          <span
            v-if="asInvitation(row).organization"
            class="flex items-center gap-1 text-xs text-fg-muted"
          >
            {{ asInvitation(row).organization?.name }}
            <UiBadge
              v-if="asInvitation(row).organization?.verified"
              size="sm"
              tone="primary"
            >
              {{ t('invitations.issuer.picker.verified') }}
            </UiBadge>
          </span>
          <span
            v-else-if="asInvitation(row).vendor"
            class="text-xs text-fg-muted"
          >{{ t('invitations.issuer.table.vendor', { name: asInvitation(row).vendor?.name ?? '' }) }}</span>
        </span>
      </template>
      <template #cell-name="{ row }">
        {{ asInvitation(row).name || '—' }}
      </template>
      <template #cell-status="{ row }">
        <span class="flex flex-col items-end gap-1 md:items-start">
          <CompetitionsIssuerInvitationStatusChip :status="asInvitation(row).status" />
          <span
            v-if="asInvitation(row).decline_reason"
            class="text-xs text-fg-muted"
          >{{ asInvitation(row).decline_reason }}</span>
        </span>
      </template>
      <template #cell-alias="{ row }">
        <span v-if="asInvitation(row).participant">{{ t('offers.participant_alias', { number: asInvitation(row).participant?.alias_no ?? 0 }) }}</span>
        <span v-else>—</span>
      </template>
      <template #cell-coverage="{ row }">
        <CompetitionsIssuerCoverageChip
          :coverage="asInvitation(row).coverage"
          :pass-status="asInvitation(row).pass_status"
        />
      </template>
      <template #cell-date="{ row }">
        <span
          v-if="lastEvent(asInvitation(row))"
          class="flex flex-col text-sm"
        >
          <span class="text-fg-muted">{{ t(`invitations.issuer.events.${lastEvent(asInvitation(row))?.key}`) }}</span>
          <UiDateTime :value="lastEvent(asInvitation(row))?.at" />
        </span>
      </template>
      <template #cell-actions="{ row }">
        <UiDropdownMenu
          v-if="menu(asInvitation(row)).length > 0"
          :label="t('invitations.issuer.actions.menu', { email: asInvitation(row).email })"
          :items="menu(asInvitation(row))"
          trigger-class="size-9 justify-center text-fg-muted hover:bg-surface-muted hover:text-fg"
          @select="key => onAction(asInvitation(row), key)"
        >
          <template #trigger>
            <UiSkeleton
              v-if="busyId === asInvitation(row).id"
              shape="circle"
              class="size-4"
            />
            <EllipsisVertical
              v-else
              :size="18"
              aria-hidden="true"
            />
            <span class="sr-only">{{ t('invitations.issuer.actions.menu', { email: asInvitation(row).email }) }}</span>
          </template>
        </UiDropdownMenu>
      </template>
      <template #empty>
        <UiEmptyState
          :title="emptyTitle ?? t('invitations.issuer.empty_title')"
          :description="emptyBody"
          :icon="Users"
        >
          <slot name="empty-action" />
        </UiEmptyState>
      </template>
    </UiTable>

    <UiConfirmDialog
      v-model:open="revokeOpen"
      :title="t('invitations.issuer.revoke.title')"
      :description="t('invitations.issuer.revoke.body', { email: revokeTarget?.email ?? '' })"
      :confirm-label="t('invitations.issuer.revoke.confirm')"
      :busy="revoking"
      :error="revokeError"
      danger
      @confirm="confirmRevoke"
    />

    <UiModal
      v-model:open="editOpen"
      :title="t('invitations.issuer.edit.title')"
      :description="editTarget?.email"
      size="sm"
    >
      <form
        class="flex flex-col gap-4"
        novalidate
        @submit.prevent="saveEdit"
      >
        <UiInput
          v-model="editName"
          :label="t('invitations.issuer.picker.name_label')"
          :maxlength="150"
        />
        <UiSwitch
          v-if="sponsorshipMode === 'selected'"
          v-model="editSponsored"
          :label="t('invitations.issuer.picker.cover_fees')"
        />
        <UiAlert
          v-if="editError"
          tone="danger"
        >
          {{ editError }}
        </UiAlert>
        <div class="flex justify-end gap-2">
          <UiButton
            variant="secondary"
            :disabled="editing"
            @click="editOpen = false"
          >
            {{ t('common.actions.cancel') }}
          </UiButton>
          <UiButton
            type="submit"
            :loading="editing"
          >
            {{ t('common.actions.save') }}
          </UiButton>
        </div>
      </form>
    </UiModal>
  </div>
</template>
