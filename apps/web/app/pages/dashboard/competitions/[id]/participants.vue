<script setup lang="ts">
import { UserPlus } from '@lucide/vue'
import { fetchSponsorship } from '~/services/billing'
import { listInvitations } from '~/services/competitions'
import type { Sponsorship } from '~/types/api/billing'
import type { Invitation, InvitationCounts, InvitationStatus, IssuerCompetition } from '~/types/api/competitions'
import { upsertInvitation } from '~/stores/competition-editor-invitations'

/**
 * W17 Participants (issuer) · `/dashboard/competitions/{id}/participants` (SCREENS §2.4): status
 * filter chips with the server counts, the invitations table (resend, revoke, remove, edit), the
 * covered-fees card, and "Invite more" (`permissions.can_invite`, before `invitation_cutoff_at`;
 * `?invite=1` opens it). Realtime `invitation.updated` upserts rows and refreshes the fee counters at
 * most once per second.
 */
const ctx = useCompetitionContext()
const { t } = useI18n()
const features = useFeatures()
const route = useRoute()
const router = useRouter()
const clock = useServerTime()

const competition = computed(() => (ctx.competition.value?.viewer_role === 'issuer' ? ctx.competition.value as IssuerCompetition : null))
useSeoMeta({ title: () => t('competitions.detail.tabs.participants') })

type Filter = 'all' | InvitationStatus
const filter = ref<Filter>('all')
const invitations = ref<Invitation[]>([])
const counts = ref<Partial<InvitationCounts>>({})
const loading = ref(true)
const loadError = ref<unknown>(null)
const sponsorship = ref<Sponsorship | null>(null)
const drawerOpen = ref(false)

async function load(): Promise<void> {
  const id = competition.value?.id
  if (!id) return
  loadError.value = null
  try {
    const result = await listInvitations(id)
    invitations.value = result.invitations
    counts.value = result.counts
  }
  catch (error) {
    loadError.value = error
  }
  finally {
    loading.value = false
  }
}

async function loadSponsorship(): Promise<void> {
  const id = competition.value?.id
  if (!id) return
  try {
    const result = await fetchSponsorship(id)
    sponsorship.value = result.mode === 'none' && result.funded_passes === 0 ? null : result
  }
  catch {
    sponsorship.value = null
  }
}

const refreshSponsorship = useThrottleFn(() => loadSponsorship(), 1000, true)

onMounted(() => {
  void load()
  void loadSponsorship()
  if (route.query.invite === '1') {
    drawerOpen.value = true
    void router.replace({ query: { ...route.query, invite: undefined } })
  }
})

ctx.on('invitationUpdated', (invitation) => {
  invitations.value = upsertInvitation(invitations.value, invitation)
  void refreshSponsorship()
  void load()
})

const STATUSES: InvitationStatus[] = ['draft', 'sent', 'viewed', 'joined', 'declined', 'revoked', 'expired']
const filters = computed(() => {
  const list: Array<{ key: Filter, count: number }> = [{ key: 'all', count: invitations.value.length }]
  for (const status of STATUSES) {
    const count = counts.value[status] ?? invitations.value.filter(item => item.status === status).length
    if (count > 0) list.push({ key: status, count })
  }
  return list
})
const visible = computed(() => (filter.value === 'all' ? invitations.value : invitations.value.filter(item => item.status === filter.value)))

const beforeCutoff = computed(() => {
  const cutoff = competition.value?.schedule.invitation_cutoff_at
  return !cutoff || Date.parse(cutoff) > clock.now()
})
const canInvite = computed(() => Boolean(competition.value?.permissions.can_invite) && beforeCutoff.value)
const sponsorshipMode = computed(() => sponsorship.value?.mode ?? competition.value?.sponsorship?.mode ?? 'none')

function onUpdated(invitation: Invitation): void {
  invitations.value = upsertInvitation(invitations.value, invitation)
  void load()
  void refreshSponsorship()
}

function onRemoved(id: string): void {
  invitations.value = invitations.value.filter(item => item.id !== id)
  void load()
}

function onSponsorshipUpdated(value: Sponsorship): void {
  sponsorship.value = value
  void ctx.refetch()
}

function onCreated(): void {
  void load()
  void refreshSponsorship()
  void ctx.refetch()
}
</script>

<template>
  <div
    v-if="competition"
    class="flex flex-col gap-6"
  >
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div
        role="group"
        :aria-label="t('invitations.issuer.filter_label')"
        class="flex flex-wrap gap-2"
      >
        <button
          v-for="item in filters"
          :key="item.key"
          type="button"
          class="inline-flex h-9 items-center gap-1.5 rounded-full border px-3 text-sm font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
          :class="filter === item.key ? 'border-primary bg-primary-soft text-primary-soft-fg' : 'border-line bg-surface text-fg-muted hover:text-fg'"
          :aria-pressed="filter === item.key"
          @click="filter = item.key"
        >
          {{ item.key === 'all' ? t('invitations.issuer.filter_all') : t(`invitations.status.${item.key}`) }}
          <bdi class="tabular-nums">{{ item.count }}</bdi>
        </button>
      </div>
      <UiButton
        v-if="canInvite"
        :icon="UserPlus"
        @click="drawerOpen = true"
      >
        {{ t('competitions.issuer.actions.invite') }}
      </UiButton>
      <p
        v-else-if="competition.permissions.can_invite && !beforeCutoff"
        class="text-sm text-fg-muted"
      >
        {{ t('invitations.issuer.cutoff_passed') }}
      </p>
    </div>

    <CompetitionsIssuerSponsorshipCard
      v-if="sponsorship && features.enabled('sponsorship')"
      :competition-id="competition.id"
      :sponsorship="sponsorship"
      :can-manage="competition.permissions.can_manage_sponsorship"
      @updated="onSponsorshipUpdated"
    />

    <UiCard
      v-if="loadError && invitations.length === 0"
      padding="none"
    >
      <UiErrorState
        :error="loadError"
        @retry="load"
      />
    </UiCard>
    <CompetitionsIssuerInvitationsPanel
      v-else
      :competition-id="competition.id"
      :invitations="visible"
      :status="competition.status"
      :invitation-cutoff-at="competition.schedule.invitation_cutoff_at"
      :can-manage="competition.permissions.can_invite || competition.permissions.can_edit"
      :sponsorship-mode="sponsorshipMode"
      :loading="loading"
      :empty-title="t('invitations.issuer.empty_title')"
      :empty-body="t('invitations.issuer.empty_body')"
      @updated="onUpdated"
      @removed="onRemoved"
    >
      <template #empty-action>
        <UiButton
          v-if="canInvite"
          :icon="UserPlus"
          @click="drawerOpen = true"
        >
          {{ t('competitions.issuer.actions.invite') }}
        </UiButton>
      </template>
    </CompetitionsIssuerInvitationsPanel>

    <CompetitionsIssuerInviteDrawer
      v-model:open="drawerOpen"
      :competition="competition"
      :existing="invitations"
      :sponsorship-mode="sponsorshipMode"
      @created="onCreated"
    />
  </div>
</template>
