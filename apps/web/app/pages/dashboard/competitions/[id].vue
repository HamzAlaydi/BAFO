<script setup lang="ts">
import { ArrowRight, BadgeCheck } from '@lucide/vue'
import type { IssuerLiveSnapshot } from '~/types/api/bidding'
import type { CompetitionStatus, ViewerRole } from '~/types/api/competitions'

/**
 * W13 Competition detail parent · `/dashboard/competitions/{id}` (SCREENS CD1, §2.4), shared by every
 * viewer. It loads `GET /competitions/{id}` through `provideCompetition(id)` (one realtime
 * subscription for the viewer's role, the `v`-ordered live snapshot, the connection state) and
 * renders the chrome around the child tab:
 *
 * - header: reference, title, direction / format / status chips (+ overlay pills), category,
 *   region, the issuer (for participants and invitees), the role's header meta (countdown, fees
 *   covered), the connection indicator for published competitions;
 * - the issuer's action bar (from `permissions`);
 * - tabs by `viewer_role` (issuer: overview, participants, Q&A, live, offers log, evaluation and
 *   award; participant: overview, live room, Q&A, my offers; invitee: none). Tab routes the viewer
 *   cannot use redirect to the overview.
 *
 * The setup wizard (`/setup/{step}`) gets a compact header without tabs or actions.
 */
definePageMeta({ layout: 'dashboard', middleware: 'auth' })

const { t } = useI18n()
const route = useRoute()
const localePath = useLocalePath()

const competitionId = computed(() => String(route.params.id ?? ''))
const ctx = provideCompetition(competitionId)
const competition = computed(() => ctx.competition.value)
const role = computed<ViewerRole | null>(() => ctx.viewerRole.value)

useSeoMeta({ title: () => competition.value?.title ?? t('competitions.detail.title') })

const basePath = computed(() => `/dashboard/competitions/${competitionId.value}`)
/** The child segment after the competition id: '', 'live', 'setup', … */
const segment = computed(() => {
  const target = localePath(basePath.value)
  const rest = route.path.startsWith(target) ? route.path.slice(target.length) : ''
  return rest.replace(/^\//, '').split('/')[0] ?? ''
})
const wizard = computed(() => segment.value === 'setup')

// ---------- Live values for the chips (fresher than the projection) ----------

const snapshot = computed(() => ctx.live.value)
const status = computed<CompetitionStatus | null>(() => snapshot.value?.status ?? competition.value?.status ?? null)
const phase = computed(() => (snapshot.value ? snapshot.value.phase : competition.value?.phase ?? null))
const effectiveCloseAt = computed(() => snapshot.value?.effective_close_at ?? (competition.value && 'effective_close_at' in competition.value.schedule ? competition.value.schedule.effective_close_at : null))
const extensionCount = computed(() => snapshot.value?.extension_count ?? (competition.value && 'extension_count' in competition.value.schedule ? competition.value.schedule.extension_count : 0))
const published = computed(() => status.value !== null && status.value !== 'draft' && role.value !== 'invitee')

// ---------- Tabs ----------

interface DetailTab {
  key: string
  path: string
  label: string
}

const ISSUER_OPEN: CompetitionStatus[] = ['scheduled', 'live', 'closed', 'bafo_round', 'awarded', 'not_awarded', 'cancelled']
const EVALUATION: CompetitionStatus[] = ['closed', 'bafo_round', 'awarded', 'not_awarded']

/** Child segments each role may open (SCREENS W13: other tabs redirect to the overview). */
const ALLOWED: Record<ViewerRole, readonly string[]> = {
  issuer: ['', 'participants', 'qa', 'live', 'offers', 'award', 'edit', 'setup'],
  participant: ['', 'live', 'qa', 'my-offers'],
  invitee: [''],
}

const tabs = computed<DetailTab[]>(() => {
  const current = competition.value
  if (!current || !role.value) return []
  const base = basePath.value
  const list: DetailTab[] = [{ key: '', path: base, label: t('competitions.detail.tabs.overview') }]
  if (role.value === 'issuer') {
    list.push({ key: 'participants', path: `${base}/participants`, label: t('competitions.detail.tabs.participants') })
    if (status.value && ISSUER_OPEN.includes(status.value)) {
      list.push(
        { key: 'qa', path: `${base}/qa`, label: t('competitions.detail.tabs.qa') },
        { key: 'live', path: `${base}/live`, label: t('competitions.detail.tabs.live') },
        { key: 'offers', path: `${base}/offers`, label: t('competitions.detail.tabs.offers') },
      )
    }
    if (status.value && EVALUATION.includes(status.value)) {
      list.push({ key: 'award', path: `${base}/award`, label: t('competitions.detail.tabs.award') })
    }
  }
  else if (role.value === 'participant') {
    list.push(
      { key: 'live', path: `${base}/live`, label: t('competitions.detail.tabs.live_room') },
      { key: 'qa', path: `${base}/qa`, label: t('competitions.detail.tabs.qa') },
      { key: 'my-offers', path: `${base}/my-offers`, label: t('competitions.detail.tabs.my_offers') },
    )
  }
  else {
    return []
  }
  return list
})

watch([role, segment], ([viewer, current]) => {
  if (!viewer || current === '') return
  if (!ALLOWED[viewer].includes(current)) void navigateTo(localePath(basePath.value), { replace: true })
}, { immediate: true })

// Keep the active tab visible when the tab row scrolls (narrow screens, long labels).
const tabNav = useTemplateRef<HTMLElement>('tabNav')
watch([segment, () => tabs.value.length], () => {
  void nextTick(() => {
    const scroller = tabNav.value
    const target = scroller?.querySelector<HTMLElement>('[aria-current="page"]')
    if (!scroller || !target) return
    // Scroll the tab row only (`scrollIntoView` would scroll the page too).
    const box = scroller.getBoundingClientRect()
    const item = target.getBoundingClientRect()
    if (item.left < box.left) scroller.scrollBy({ left: item.left - box.left - 16 })
    else if (item.right > box.right) scroller.scrollBy({ left: item.right - box.right + 16 })
  })
}, { immediate: true })

/** Participants and invitees go back to "Participating", issuers to their list. */
const backPath = computed(() => (role.value && role.value !== 'issuer' ? '/dashboard/participating' : '/dashboard/competitions'))

// The side navigation marks the same entry as current (the URL alone would pick "My competitions").
const navHighlight = useNavHighlight()
watch(role, (viewer) => {
  navHighlight.value = viewer && viewer !== 'issuer' ? '/dashboard/participating' : null
}, { immediate: true })
onBeforeUnmount(() => {
  navHighlight.value = null
})

const notFound = computed(() => ctx.error.value?.isNotFound === true || ctx.error.value?.code === 'not_found')
const issuerLive = computed(() => (role.value === 'issuer' && snapshot.value && 'ranking' in snapshot.value ? snapshot.value as IssuerLiveSnapshot : null))
</script>

<template>
  <div class="flex flex-col gap-6">
    <NuxtLinkLocale
      :to="backPath"
      class="inline-flex items-center gap-1.5 self-start text-sm font-semibold text-link hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
    >
      <ArrowRight
        :size="16"
        class="ltr:rotate-180"
        aria-hidden="true"
      />
      {{ backPath === '/dashboard/participating' ? t('nav.participating') : t('competitions.detail.back_issuer') }}
    </NuxtLinkLocale>

    <!-- L: header skeleton -->
    <div
      v-if="ctx.loading.value && !competition"
      class="flex flex-col gap-3"
      :aria-label="t('common.loading')"
    >
      <UiSkeleton class="h-4 w-40" />
      <UiSkeleton class="h-8 w-3/4" />
      <div class="flex gap-2">
        <UiSkeleton class="h-7 w-28 rounded-full" />
        <UiSkeleton class="h-7 w-20 rounded-full" />
        <UiSkeleton class="h-7 w-24 rounded-full" />
      </div>
      <UiSkeleton class="mt-4 h-48 w-full" />
    </div>

    <!-- N / X -->
    <UiCard
      v-else-if="!competition && notFound"
      padding="none"
    >
      <UiNotFoundState :description="t('competitions.detail.not_found')" />
    </UiCard>
    <UiCard
      v-else-if="!competition"
      padding="none"
    >
      <UiErrorState
        :error="ctx.error.value"
        :retrying="ctx.loading.value"
        @retry="ctx.refetch"
      />
    </UiCard>

    <template v-else>
      <header class="flex flex-col gap-4">
        <div class="flex flex-col gap-2">
          <p
            v-if="competition.reference_no"
            class="text-sm text-fg-muted"
          >
            <bdi class="tabular-nums">{{ competition.reference_no }}</bdi>
          </p>
          <h1
            class="text-2xl font-bold text-balance break-words text-fg"
            :title="competition.title"
          >
            {{ competition.title }}
          </h1>
          <div class="flex flex-wrap items-center gap-2">
            <CompetitionsDirectionChip :direction="competition.direction" />
            <CompetitionsFormatChip :format="competition.format" />
            <CompetitionsStatusChip
              v-if="status"
              :status="status"
              :phase="phase"
              :effective-close-at="effectiveCloseAt"
              :extension-count="extensionCount"
            />
            <UiConnectionIndicator
              v-if="published && !wizard"
              :state="ctx.connection.value"
            />
          </div>
          <p class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-fg-muted">
            <span>{{ t('competitions.detail.category') }} <span class="text-fg">{{ competition.category?.name }}</span></span>
            <span>{{ t('competitions.detail.region') }} <span class="text-fg">{{ competition.region?.name }}</span></span>
            <span
              v-if="role !== 'issuer'"
              class="inline-flex items-center gap-1.5"
            >
              {{ t('competitions.detail.issuer') }}
              <UiOrgLogo
                :name="competition.issuer.name"
                :src="competition.issuer.logo_url"
                size="sm"
              />
              <span class="text-fg">{{ competition.issuer.name }}</span>
              <BadgeCheck
                v-if="competition.issuer.verified"
                :size="16"
                class="text-brand"
                :aria-label="t('invitations.issuer.picker.verified')"
              />
            </span>
          </p>
        </div>
        <CompetitionsIssuerHeaderMeta
          v-if="role === 'issuer' && !wizard && segment !== 'live'"
          :live="issuerLive"
        />
        <CompetitionsParticipantHeaderMeta
          v-else-if="role !== 'issuer'"
          :hide-countdown="segment === 'live'"
        />
        <CompetitionsIssuerActionBar v-if="role === 'issuer' && !wizard" />
      </header>

      <UiAlert
        v-if="ctx.error.value && !notFound"
        tone="warning"
      >
        {{ t('common.states.stale_data') }}
      </UiAlert>

      <nav
        v-if="tabs.length > 1 && !wizard"
        ref="tabNav"
        :aria-label="t('competitions.detail.tabs.label')"
        class="relative -mb-2 flex gap-1 overflow-x-auto border-b border-line [scrollbar-width:thin]"
      >
        <NuxtLinkLocale
          v-for="tab in tabs"
          :key="tab.key"
          :to="tab.path"
          class="relative inline-flex h-11 shrink-0 items-center border-b-2 px-3 text-[0.9375rem] font-semibold whitespace-nowrap transition-colors focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ring"
          :class="segment === tab.key ? 'border-brand text-fg' : 'border-transparent text-fg-muted hover:text-fg'"
          :aria-current="segment === tab.key ? 'page' : undefined"
        >
          {{ tab.label }}
        </NuxtLinkLocale>
      </nav>

      <NuxtPage />
    </template>
  </div>
</template>
