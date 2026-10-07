<script setup lang="ts">
import { Ban, CalendarPlus, Gavel, Pencil, Send, Trash2, UserPlus, Wand2 } from '@lucide/vue'
import { cancelCompetition, deleteCompetition } from '~/services/competitions'
import type { CloseReasonRequest, IssuerCompetition } from '~/types/api/competitions'

/**
 * Issuer action bar of the detail header (SCREENS W13), rendered only from the competition's
 * `permissions` (S10): continue setup, publish and delete (draft); edit, invite, extend, cancel
 * (scheduled, live, BAFO round); evaluation and award (from closed). Destructive actions go through
 * named confirmations; nothing changes before the server answers (CD9).
 */
const ctx = useCompetitionContext()
const { t } = useI18n()
const features = useFeatures()
const toast = useToast()
const { message } = useErrorMessage()
const localePath = useLocalePath()

const competition = computed(() => (ctx.competition.value?.viewer_role === 'issuer' ? ctx.competition.value as IssuerCompetition : null))
const permissions = computed(() => competition.value?.permissions)
const status = computed(() => ctx.live.value?.status ?? competition.value?.status ?? null)
const base = computed(() => (competition.value ? `/dashboard/competitions/${competition.value.id}` : ''))

const extendOpen = ref(false)
const cancelOpen = ref(false)
const deleteOpen = ref(false)
const deleting = ref(false)
const deleteError = ref<string | null>(null)

const route = useRoute()
const onAwardTab = computed(() => /\/award\/?$/.test(route.path))
const onParticipantsTab = computed(() => /\/participants\/?$/.test(route.path))
const showAward = computed(() => {
  const p = permissions.value
  return !onAwardTab.value && Boolean(p && (p.can_award || p.can_start_bafo || p.can_revoke_award || p.can_close_without_award))
    && ['closed', 'bafo_round', 'awarded'].includes(status.value ?? '')
})

function onExtended(updated: IssuerCompetition): void {
  ctx.competition.value = updated
  toast.success(t('competitions.issuer.extend.done'))
  void ctx.resync()
}

async function submitCancel(body: CloseReasonRequest): Promise<void> {
  if (!competition.value) return
  ctx.competition.value = await cancelCompetition(competition.value.id, body)
  toast.success(t('competitions.issuer.cancel.done'))
  void ctx.resync()
}

async function confirmDelete(): Promise<void> {
  if (!competition.value) return
  deleting.value = true
  deleteError.value = null
  try {
    await deleteCompetition(competition.value.id)
    deleteOpen.value = false
    toast.success(t('competitions.issuer.delete.done'))
    await navigateTo(localePath({ path: '/dashboard/competitions', query: { status_group: 'draft' } }))
  }
  catch (error) {
    deleteError.value = message(error)
  }
  finally {
    deleting.value = false
  }
}
</script>

<template>
  <div
    v-if="competition && permissions"
    class="flex flex-wrap items-center gap-2"
  >
    <template v-if="status === 'draft'">
      <UiButton
        v-if="permissions.can_edit"
        :to="`${base}/setup`"
        :icon="Wand2"
      >
        {{ t('competitions.issuer.actions.continue_setup') }}
      </UiButton>
      <UiButton
        v-if="permissions.can_publish"
        :to="`${base}/setup/review`"
        variant="secondary"
        :icon="Send"
        flip-icons
      >
        {{ t('competitions.issuer.actions.publish') }}
      </UiButton>
    </template>
    <UiButton
      v-if="showAward"
      :to="`${base}/award`"
      :icon="Gavel"
    >
      {{ t('competitions.issuer.actions.award') }}
    </UiButton>
    <UiButton
      v-if="permissions.can_edit && (status === 'scheduled' || status === 'live')"
      :to="`${base}/edit`"
      variant="secondary"
      :icon="Pencil"
    >
      {{ t('competitions.issuer.actions.edit') }}
    </UiButton>
    <UiButton
      v-if="permissions.can_invite && status !== 'draft' && !onParticipantsTab"
      :to="{ path: `${base}/participants`, query: { invite: '1' } }"
      variant="secondary"
      :icon="UserPlus"
    >
      {{ t('competitions.issuer.actions.invite') }}
    </UiButton>
    <UiButton
      v-if="permissions.can_extend && status === 'live' && features.enabled('extend_competition')"
      variant="secondary"
      :icon="CalendarPlus"
      @click="extendOpen = true"
    >
      {{ t('competitions.issuer.actions.extend') }}
    </UiButton>
    <UiButton
      v-if="permissions.can_cancel"
      variant="danger-ghost"
      :icon="Ban"
      @click="cancelOpen = true"
    >
      {{ t('competitions.issuer.actions.cancel') }}
    </UiButton>
    <UiButton
      v-if="permissions.can_delete && status === 'draft'"
      variant="danger-ghost"
      :icon="Trash2"
      @click="deleteError = null; deleteOpen = true"
    >
      {{ t('competitions.issuer.actions.delete') }}
    </UiButton>

    <CompetitionsIssuerExtendDialog
      v-model:open="extendOpen"
      :competition="competition"
      :effective-close-at="ctx.live.value?.effective_close_at"
      @extended="onExtended"
    />
    <CompetitionsIssuerReasonDialog
      v-model:open="cancelOpen"
      kind="cancel"
      :title="t('competitions.issuer.cancel.title')"
      :description="t('competitions.issuer.cancel.description')"
      :confirm-label="t('competitions.issuer.cancel.confirm')"
      :cancel-label="t('competitions.issuer.cancel.keep')"
      :submit="submitCancel"
    />
    <UiConfirmDialog
      v-model:open="deleteOpen"
      :title="t('competitions.issuer.delete.title')"
      :description="t('competitions.issuer.delete.description')"
      :confirm-label="t('competitions.issuer.delete.confirm')"
      :cancel-label="t('competitions.issuer.delete.keep')"
      :busy="deleting"
      :error="deleteError"
      danger
      @confirm="confirmDelete"
    />
  </div>
</template>
