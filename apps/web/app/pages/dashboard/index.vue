<script setup lang="ts">
import { Award, ClipboardList, FilePen, Handshake, Hourglass, Inbox, Plus, Radio, Send, Tags, Trophy, Users } from '@lucide/vue'
import type { Component } from 'vue'
import { CREATE_COMPETITION_ACTION } from '~/config/navigation'
import type { Home } from '~/types/api/competitions'

/**
 * W10 Overview · `/dashboard` (SCREENS §2.4): `GET /home` stats for the issuer and participant sides,
 * subscription and seats, recent activity and quick actions. Refetched on `notification.created`
 * (debounced 2 s) and when the window regains focus.
 */
definePageMeta({ layout: 'dashboard', middleware: 'auth' })

const { t } = useI18n()
const auth = useAuthStore()
const features = useFeatures()
const store = useHomeStore()
const notifications = useNotificationsStore()

useSeoMeta({ title: () => t('nav.overview') })

onMounted(() => {
  void store.load()
})

const refetchSoon = useDebounceFn(() => store.load(), 2000)
notifications.onCreated(() => void refetchSoon())
useEventListener('focus', () => void store.load())

interface Tile {
  key: string
  icon: Component
  value: (home: Home) => number
  to?: { path: string, query?: Record<string, string> }
}

const issuerTiles: Tile[] = [
  { key: 'active_competitions', icon: ClipboardList, value: h => h.issuer.active_competitions, to: { path: '/dashboard/competitions', query: { status_group: 'active' } } },
  { key: 'draft_competitions', icon: FilePen, value: h => h.issuer.draft_competitions, to: { path: '/dashboard/competitions', query: { status_group: 'draft' } } },
  { key: 'live_now', icon: Radio, value: h => h.issuer.live_now, to: { path: '/dashboard/competitions', query: { status: 'live' } } },
  { key: 'awaiting_award', icon: Hourglass, value: h => h.issuer.awaiting_award, to: { path: '/dashboard/competitions', query: { status: 'closed' } } },
  { key: 'offers_received_30d', icon: Tags, value: h => h.issuer.offers_received_30d },
]

const participantTiles: Tile[] = [
  { key: 'pending_invitations', icon: Inbox, value: h => h.participant.pending_invitations, to: { path: '/dashboard/participating' } },
  { key: 'active_participations', icon: Handshake, value: h => h.participant.active_participations, to: { path: '/dashboard/participating', query: { status_group: 'active' } } },
  { key: 'offers_submitted_30d', icon: Send, value: h => h.participant.offers_submitted_30d },
  { key: 'awards_won', icon: Trophy, value: h => h.participant.awards_won, to: { path: '/dashboard/participating', query: { status_group: 'ended' } } },
]

const home = computed(() => store.home)
const firstLoad = computed(() => store.loading && !home.value)
const canCreate = computed(() => auth.can(CREATE_COMPETITION_ACTION.permission))
const canIssue = computed(() => auth.entitlements?.can_issue === true)
const subscription = computed(() => home.value?.subscription ?? null)
const greetingName = computed(() => auth.user?.name?.split(/\s+/)[0] ?? '')
</script>

<template>
  <div class="flex flex-col gap-8">
    <UiPageHeader
      :title="greetingName ? t('home.greeting', { name: greetingName }) : t('nav.overview')"
      :description="t('home.subtitle')"
    >
      <template
        v-if="canCreate"
        #actions
      >
        <UiButton
          v-if="canIssue"
          :to="CREATE_COMPETITION_ACTION.to"
          :icon="Plus"
        >
          {{ t('nav.create_competition') }}
        </UiButton>
        <UiButton
          to="/dashboard/participating"
          variant="secondary"
          :icon="Inbox"
        >
          {{ t('home.quick_actions.view_invitations') }}
        </UiButton>
      </template>
    </UiPageHeader>

    <UiCard
      v-if="store.error && !home"
      padding="none"
    >
      <UiErrorState
        :error="store.error"
        :retrying="store.loading"
        @retry="store.load()"
      />
    </UiCard>

    <template v-else>
      <UiAlert
        v-if="store.error && home"
        tone="warning"
      >
        {{ t('common.states.stale_data') }}
      </UiAlert>

      <section
        class="flex flex-col gap-3"
        aria-labelledby="home-issuer"
      >
        <h2
          id="home-issuer"
          class="text-base font-bold text-fg"
        >
          {{ t('home.sections.issuer') }}
        </h2>
        <ul class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5">
          <li
            v-for="tile in issuerTiles"
            :key="tile.key"
          >
            <UiStatTile
              :label="t(`home.stats.issuer.${tile.key}`)"
              :value="home ? tile.value(home) : null"
              :icon="tile.icon"
              :to="tile.to"
              :loading="firstLoad"
            />
          </li>
        </ul>
      </section>

      <section
        class="flex flex-col gap-3"
        aria-labelledby="home-participant"
      >
        <h2
          id="home-participant"
          class="text-base font-bold text-fg"
        >
          {{ t('home.sections.participant') }}
        </h2>
        <ul class="grid grid-cols-2 gap-3 xl:grid-cols-4">
          <li
            v-for="tile in participantTiles"
            :key="tile.key"
          >
            <UiStatTile
              :label="t(`home.stats.participant.${tile.key}`)"
              :value="home ? tile.value(home) : null"
              :icon="tile.icon"
              :to="tile.to"
              :loading="firstLoad"
            />
          </li>
        </ul>
      </section>

      <div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)]">
        <div class="flex flex-col gap-6">
          <UiCard :title="t('home.subscription.title')">
            <div
              v-if="firstLoad"
              class="flex flex-col gap-3"
            >
              <UiSkeleton class="h-5 w-1/2" />
              <UiSkeleton class="h-2 w-full" />
            </div>
            <div
              v-else-if="subscription"
              class="flex flex-col gap-3"
            >
              <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-lg font-bold text-fg">
                  {{ subscription.plan.name }}
                </p>
                <UiBadge
                  :tone="subscription.status === 'active' ? 'primary' : 'neutral'"
                  :icon="Award"
                >
                  {{ t(`home.subscription.status.${subscription.status}`) }}
                </UiBadge>
              </div>
              <UiMeter
                :value="subscription.days_left"
                :max="subscription.total_days"
                :label="t('home.subscription.days_left_label')"
                :value-text="t('home.subscription.days_left', { count: subscription.days_left }, subscription.days_left)"
                warn-when="low"
              />
              <p class="flex flex-wrap justify-between gap-2 text-sm text-fg-muted">
                <span>{{ t('home.subscription.days_left', { count: subscription.days_left }, subscription.days_left) }}</span>
                <span>{{ t('home.subscription.ends_at') }} <UiDateTime
                  :value="subscription.ends_at"
                  format="date"
                  class="font-semibold text-fg"
                /></span>
              </p>
            </div>
            <p
              v-else
              class="text-sm text-fg-muted"
            >
              {{ t('home.subscription.none') }}
            </p>
            <template
              v-if="auth.can('billing.view')"
              #footer
            >
              <UiButton
                to="/dashboard/billing"
                variant="ghost"
                size="sm"
              >
                {{ t('home.subscription.manage') }}
              </UiButton>
            </template>
          </UiCard>

          <UiCard :title="t('home.team.title')">
            <div
              v-if="firstLoad"
              class="flex flex-col gap-3"
            >
              <UiSkeleton class="h-5 w-1/3" />
              <UiSkeleton class="h-2 w-full" />
            </div>
            <div
              v-else-if="home"
              class="flex flex-col gap-3"
            >
              <p class="flex items-center gap-2 text-sm text-fg">
                <Users
                  :size="16"
                  class="text-fg-muted"
                  aria-hidden="true"
                />
                <span class="tabular-nums">{{ t('home.team.seats', { used: home.team.members, total: home.team.seats_total }) }}</span>
              </p>
              <UiMeter
                :value="home.team.members"
                :max="home.team.seats_total"
                :label="t('home.team.title')"
                :value-text="t('home.team.seats', { used: home.team.members, total: home.team.seats_total })"
                warn-when="high"
              />
            </div>
            <template
              v-if="auth.can('team.manage') && features.enabled('team_management')"
              #footer
            >
              <UiButton
                to="/dashboard/team"
                variant="ghost"
                size="sm"
              >
                {{ t('home.team.manage') }}
              </UiButton>
            </template>
          </UiCard>
        </div>

        <UiCard :title="t('home.activity.title')">
          <div
            v-if="firstLoad"
            class="flex flex-col gap-4"
          >
            <UiSkeleton
              v-for="n in 4"
              :key="n"
              :lines="2"
            />
          </div>
          <HomeActivityList
            v-else
            :activities="home?.activities ?? []"
          />
        </UiCard>
      </div>
    </template>
  </div>
</template>
