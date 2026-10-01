<script setup lang="ts">
import { ExternalLink } from '@lucide/vue'
import { fetchCompetition, joinInvitation } from '~/services/competitions'
import type { Competition, ParticipationAccess } from '~/types/api/competitions'

/**
 * `JoinDialog` (SCREENS W14 invitee, W23): the rules summary, a link to the published
 * `competition_rules` terms (new tab) and the required «أوافق على شروط المنافسة» checkbox →
 * `POST /invitations/{id}/join {accept_terms: true}`. The response is the participant projection;
 * the caller re-renders from it. Nothing changes before the server answers (CD9).
 *
 * Errors: `plan_required` (with `details.access`), `join_deadline_passed`, `already_participating`,
 * `invalid_state_transition` (the caller refetches), `terms_not_accepted`.
 */
const props = defineProps<{
  invitationId: string
  competitionId: string
  /** The server's rules summary when the caller has it; otherwise it is loaded. */
  rulesSummary?: string[] | null
}>()

const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{
  joined: [competition: Competition]
  planRequired: [access: ParticipationAccess | null]
  stale: []
}>()

const { t } = useI18n()
const localePath = useLocalePath()
const { message } = useErrorMessage()

const accepted = ref(false)
const busy = ref(false)
const error = ref<string | null>(null)
const loadedLines = ref<string[] | null>(null)
const loadingLines = ref(false)

const lines = computed(() => props.rulesSummary ?? loadedLines.value ?? [])

async function loadRules(): Promise<void> {
  if (props.rulesSummary || loadedLines.value) return
  loadingLines.value = true
  try {
    const competition = await fetchCompetition(props.competitionId)
    loadedLines.value = competition.rules_summary
  }
  catch {
    loadedLines.value = null
  }
  finally {
    loadingLines.value = false
  }
}

watch(open, (isOpen) => {
  if (!isOpen) return
  accepted.value = false
  error.value = null
  void loadRules()
}, { immediate: true })

function accessFrom(details: Record<string, unknown>): ParticipationAccess | null {
  const access = details.access
  if (typeof access !== 'object' || access === null || !('state' in access)) return null
  return access as ParticipationAccess
}

async function join(): Promise<void> {
  if (busy.value) return
  if (!accepted.value) {
    error.value = t('errors.terms_not_accepted')
    return
  }
  busy.value = true
  error.value = null
  try {
    const competition = await joinInvitation(props.invitationId)
    open.value = false
    emit('joined', competition)
  }
  catch (cause) {
    error.value = message(cause)
    if (!(cause instanceof ApiError)) return
    if (cause.code === 'plan_required') emit('planRequired', accessFrom(cause.details))
    else if (['join_deadline_passed', 'already_participating', 'invalid_state_transition'].includes(cause.code)) emit('stale')
  }
  finally {
    busy.value = false
  }
}
</script>

<template>
  <UiConfirmDialog
    v-model:open="open"
    :title="t('invitations.join.title')"
    :description="t('invitations.join.description')"
    :confirm-label="t('invitations.join.confirm')"
    :busy="busy"
    :confirm-disabled="!accepted"
    :error="error"
    @confirm="join"
  >
    <div
      class="flex flex-col gap-4"
      data-testid="join-dialog"
    >
      <div
        v-if="loadingLines"
        class="flex flex-col gap-2"
        aria-busy="true"
        :aria-label="t('common.loading')"
      >
        <UiSkeleton :lines="3" />
      </div>
      <div
        v-else-if="lines.length > 0"
        class="max-h-64 overflow-y-auto rounded-md bg-surface-muted p-3"
      >
        <CompetitionsRulesSummary
          :lines="lines"
          :title="t('invitations.landing.rules_title')"
        />
      </div>
      <a
        :href="localePath('/legal/competition_rules')"
        target="_blank"
        rel="noopener"
        class="link inline-flex items-center gap-1.5 text-sm font-semibold"
      >
        {{ t('invitations.join.terms_link') }}
        <ExternalLink
          :size="14"
          aria-hidden="true"
        />
        <span class="sr-only">{{ t('competitions.participant.new_tab') }}</span>
      </a>
      <UiCheckbox
        v-model="accepted"
        :label="t('invitations.join.accept')"
        required
        data-testid="accept-terms"
      />
    </div>
  </UiConfirmDialog>
</template>
