<script setup lang="ts">
import { UserPlus } from '@lucide/vue'
import { createInvitations, listInvitations } from '~/services/competitions'
import type { Invitation, IssuerCompetition } from '~/types/api/competitions'
import { stagedRowErrors, stagedToInput, upsertInvitation, type StagedInvitation } from '~/stores/competition-editor-invitations'

/**
 * Wizard step 6 «المتنافسون» (SCREENS W15 `participants`): the invite picker, "Add {n} invitations"
 * (all-or-nothing, per-row errors put back on the staged rows), the current draft invitations with
 * name edit and removal, and the counter against the minimum participants.
 */
const props = defineProps<{ competition: IssuerCompetition }>()
const emit = defineEmits<{ changed: [] }>()

const { t } = useI18n()
const toast = useToast()
const { message } = useErrorMessage()

const invitations = ref<Invitation[]>([])
const loading = ref(true)
const loadError = ref<unknown>(null)
const staged = ref<StagedInvitation[]>([])
const sending = ref(false)
const formError = ref<string | null>(null)

const mode = computed(() => props.competition.sponsorship?.mode ?? 'none')
const active = computed(() => invitations.value.filter(item => item.status !== 'revoked'))
const min = computed(() => props.competition.rules.min_participants)
const reached = computed(() => active.value.length >= min.value)
const sendable = computed(() => staged.value.filter(row => row.kind !== 'email' || isEmail(row.email ?? '')))

async function load(): Promise<void> {
  loadError.value = null
  try {
    invitations.value = (await listInvitations(props.competition.id)).invitations
  }
  catch (error) {
    loadError.value = error
  }
  finally {
    loading.value = false
  }
}

onMounted(load)

async function send(): Promise<void> {
  const rows = sendable.value
  if (rows.length === 0) return
  sending.value = true
  formError.value = null
  try {
    const created = await createInvitations(props.competition.id, stagedToInput(rows, mode.value === 'selected'))
    invitations.value = created.reduce((list, invitation) => upsertInvitation(list, invitation), invitations.value)
    const sentKeys = new Set(rows.map(row => row.key))
    staged.value = staged.value.filter(row => !sentKeys.has(row.key))
    toast.success(t('invitations.issuer.toasts.added', { count: created.length }, created.length))
    emit('changed')
  }
  catch (error) {
    if (error instanceof ApiError && error.isValidation) {
      const mapped = stagedRowErrors(error, rows.length)
      const byKey = new Map(rows.map((row, index) => [row.key, mapped.rows[index] ?? null]))
      staged.value = staged.value.map((row) => {
        const problem = byKey.get(row.key)
        return problem ? { ...row, error: problem.message, errorCode: problem.code } : row
      })
      formError.value = mapped.unmatched[0] ?? t('invitations.issuer.rows_rejected')
    }
    else {
      formError.value = message(error)
    }
  }
  finally {
    sending.value = false
  }
}

function onUpdated(invitation: Invitation): void {
  invitations.value = upsertInvitation(invitations.value, invitation)
  emit('changed')
}

function onRemoved(id: string): void {
  invitations.value = invitations.value.filter(item => item.id !== id)
  emit('changed')
}
</script>

<template>
  <div class="flex flex-col gap-6">
    <UiCard>
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <p class="text-sm text-fg-muted">
            {{ t('invitations.issuer.counter_label') }}
          </p>
          <p class="text-lg font-bold text-fg">
            {{ t('invitations.issuer.counter', { count: active.length, min }) }}
          </p>
        </div>
        <UiBadge
          :tone="reached ? 'primary' : 'warning'"
          size="md"
        >
          {{ reached ? t('invitations.issuer.min_reached') : t('invitations.issuer.min_missing', { count: min - active.length }, min - active.length) }}
        </UiBadge>
      </div>
    </UiCard>

    <UiCard :title="t('invitations.issuer.add_title')">
      <div class="flex flex-col gap-5">
        <CompetitionsIssuerInvitePicker
          v-model="staged"
          :competition-id="competition.id"
          :existing="invitations"
          :sponsored-selectable="mode === 'selected'"
          :category-id="competition.category?.id ?? null"
          :region-id="competition.region?.id ?? null"
        />
        <UiAlert
          v-if="formError"
          tone="danger"
        >
          {{ formError }}
        </UiAlert>
        <div
          v-if="staged.length > 0"
          class="flex justify-end"
        >
          <UiButton
            :icon="UserPlus"
            :loading="sending"
            :disabled="sendable.length === 0"
            @click="send"
          >
            {{ t('invitations.issuer.add_n', { count: sendable.length }, sendable.length) }}
          </UiButton>
        </div>
      </div>
    </UiCard>

    <section class="flex flex-col gap-3">
      <h3 class="text-base font-bold text-fg">
        {{ t('invitations.issuer.current_title') }}
      </h3>
      <UiErrorState
        v-if="loadError && invitations.length === 0"
        :error="loadError"
        compact
        @retry="load"
      />
      <CompetitionsIssuerInvitationsPanel
        v-else
        :competition-id="competition.id"
        :invitations="invitations"
        :status="competition.status"
        :invitation-cutoff-at="competition.schedule.invitation_cutoff_at"
        :can-manage="competition.permissions.can_invite || competition.permissions.can_edit"
        :sponsorship-mode="mode"
        :loading="loading"
        :empty-title="t('invitations.issuer.empty_title')"
        :empty-body="t('invitations.issuer.empty_draft_body')"
        @updated="onUpdated"
        @removed="onRemoved"
      />
    </section>
  </div>
</template>
