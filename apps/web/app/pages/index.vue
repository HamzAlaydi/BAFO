<script setup lang="ts">
import type { Component } from 'vue'
import {
  ArrowLeft,
  ArrowRight,
  BellRing,
  BookOpen,
  ChevronDown,
  CircleCheck,
  ClipboardCheck,
  EyeOff,
  FileSpreadsheet,
  FileText,
  Gavel,
  History,
  KeyRound,
  Lock,
  Mail,
  MessagesSquare,
  PenLine,
  Send,
  ShieldCheck,
  Ticket,
  Timer,
  Trophy,
  Users,
  Webhook,
} from '@lucide/vue'
import { fetchPlans } from '~/services/billing'
import { submitContact } from '~/services/platform'
import type { TabItem } from '~/types/ui'

/**
 * W01 Landing · `/` (SSR; SCREENS §2.4). Hero, how it works for issuers and participants, tender and
 * auction, live and sealed formats, sponsored participation, ERP connectivity, trust, a plans teaser
 * (`GET /plans`, SSR-fetched, hidden on failure), FAQ, contact (`POST /contact` with the honeypot)
 * and a closing call to action. SEO: localised title and description, Open Graph per locale,
 * hreflang alternates (app.vue) and schema.org JSON-LD.
 */
const { t } = useI18n()
const locale = useAppLocale()
const money = useMoney()
const config = useRuntimeConfig()
const requestUrl = useRequestURL()
const { message, bind } = useErrorMessage()

const origin = computed(() => requestUrl.origin)
const apiOrigin = computed(() => apiOriginFrom(config.public.apiBase))
const forwardArrow = computed<Component>(() => (locale.value === 'ar' ? ArrowLeft : ArrowRight))

// ---------- SEO ----------

const ogImage = computed(() => `${origin.value}${locale.value === 'ar' ? '/brand/lockup-ar-light.png' : '/brand/wordmark-en-charcoal.png'}`)

useSeoMeta({
  title: () => t('landing.meta.title'),
  description: () => t('landing.meta.description'),
  ogTitle: () => t('landing.meta.og_title'),
  ogDescription: () => t('landing.meta.description'),
  ogType: 'website',
  ogUrl: () => `${origin.value}/${locale.value}`,
  ogImage: () => ogImage.value,
  ogImageAlt: () => t('landing.meta.og_image_alt'),
  ogLocale: () => (locale.value === 'ar' ? 'ar_SA' : 'en_US'),
  ogLocaleAlternate: () => [locale.value === 'ar' ? 'en_US' : 'ar_SA'],
  twitterCard: 'summary_large_image',
  twitterTitle: () => t('landing.meta.og_title'),
  twitterDescription: () => t('landing.meta.description'),
  twitterImage: () => ogImage.value,
})

const FAQ_KEYS = ['what', 'tender_auction', 'sealed', 'identities', 'clock', 'sponsored', 'plans', 'erp', 'mobile'] as const

const faq = computed(() => FAQ_KEYS.map(key => ({
  key,
  question: t(`landing.faq.items.${key}.question`),
  answer: t(`landing.faq.items.${key}.answer`),
})))

useHead(() => ({
  meta: [{ key: 'keywords', name: 'keywords', content: t('landing.meta.keywords') }],
  script: [
    {
      key: 'landing-jsonld',
      type: 'application/ld+json',
      innerHTML: JSON.stringify([
        {
          '@context': 'https://schema.org',
          '@type': 'Organization',
          'name': t('common.app.name'),
          'alternateName': 'BAFO',
          'url': `${origin.value}/${locale.value}`,
          'logo': `${origin.value}/brand/mark-color-1024.png`,
          'slogan': t('common.app.tagline'),
        },
        {
          '@context': 'https://schema.org',
          '@type': 'SoftwareApplication',
          'name': t('common.app.name'),
          'applicationCategory': 'BusinessApplication',
          'operatingSystem': 'Web, iOS, Android',
          'description': t('landing.meta.description'),
          'inLanguage': locale.value,
        },
        {
          '@context': 'https://schema.org',
          '@type': 'FAQPage',
          'inLanguage': locale.value,
          'mainEntity': faq.value.map(item => ({
            '@type': 'Question',
            'name': item.question,
            'acceptedAnswer': { '@type': 'Answer', 'text': item.answer },
          })),
        },
      ]),
    },
  ],
}))

// ---------- Hero preview ----------

// A sample tender card. The deadline is fixed once on the server and reused on the client.
const previewEndsAt = useState('landing:preview-ends-at', () => new Date(Date.now() + (2 * 3600 + 14 * 60) * 1000).toISOString())

// ---------- How it works ----------

type Audience = 'issuer' | 'participant'
const audience = ref<Audience>('issuer')
const audienceTabs = computed<TabItem[]>(() => [
  { key: 'issuer', label: t('landing.how.tabs.issuer'), icon: Gavel },
  { key: 'participant', label: t('landing.how.tabs.participant'), icon: Users },
])

const STEP_ICONS: Record<Audience, Component[]> = {
  issuer: [PenLine, Send, Timer, Trophy],
  participant: [Mail, MessagesSquare, Send, BellRing],
}

function steps(kind: Audience) {
  return (['one', 'two', 'three', 'four'] as const).map((key, index) => ({
    key,
    number: index + 1,
    icon: STEP_ICONS[kind][index] ?? CircleCheck,
    title: t(`landing.how.${kind}.${key}.title`),
    body: t(`landing.how.${kind}.${key}.body`),
  }))
}

// ---------- Trust ----------

const trust = computed(() => [
  { key: 'clock', icon: Timer },
  { key: 'privacy', icon: EyeOff },
  { key: 'sealed', icon: Lock },
  { key: 'ledger', icon: History },
  { key: 'award', icon: ClipboardCheck },
  { key: 'invoices', icon: FileText },
].map(item => ({
  ...item,
  title: t(`landing.trust.items.${item.key}.title`),
  body: t(`landing.trust.items.${item.key}.body`),
})))

// ---------- Plans teaser (SSR; hidden on failure) ----------

const { data: plans } = await useAsyncData(
  'landing:plans',
  () => fetchPlans().catch(() => null),
  { watch: [locale], default: () => null },
)

const teaserPlans = computed(() => (plans.value ?? []).filter(plan => !plan.is_custom && plan.monthly_price_minor !== null).slice(0, 3))
const hasCustomPlan = computed(() => (plans.value ?? []).some(plan => plan.is_custom))

// ---------- Contact ----------

const contact = reactive({ name: '', email: '', phone: null as string | null, company: '', subject: '', message: '', website_url: '' })
const contactSubmitted = ref(false)
const contactBusy = ref(false)
const contactSent = ref(false)
const contactError = ref<string | null>(null)
const contactServerErrors = ref<Record<string, string>>({})

const contactErrors = computed((): Record<string, string | null> => {
  if (!contactSubmitted.value) return {}
  return {
    name: contact.name.trim() ? null : t('validation.required'),
    email: !contact.email.trim() ? t('validation.required') : isEmail(contact.email.trim()) ? null : t('validation.email'),
    subject: contact.subject.trim() ? null : t('validation.required'),
    message: contact.message.trim() ? null : t('validation.required'),
  }
})

const contactFieldError = (field: string) => contactServerErrors.value[field] ?? contactErrors.value[field] ?? null

async function sendContact(): Promise<void> {
  contactSubmitted.value = true
  contactError.value = null
  contactServerErrors.value = {}
  if (Object.values(contactErrors.value).some(Boolean) || contactBusy.value) return
  contactBusy.value = true
  try {
    await submitContact({
      name: contact.name.trim(),
      email: contact.email.trim(),
      phone: contact.phone,
      company: contact.company.trim() || null,
      subject: contact.subject.trim(),
      message: contact.message.trim(),
      website_url: contact.website_url,
    })
    contactSent.value = true
  }
  catch (error) {
    const bound = bind(error, ['name', 'email', 'phone', 'company', 'subject', 'message'])
    contactServerErrors.value = bound.fields
    contactError.value = bound.unmatched[0] ?? (Object.keys(bound.fields).length > 0 ? null : message(error))
  }
  finally {
    contactBusy.value = false
  }
}
</script>

<template>
  <div>
    <!-- Hero -->
    <section
      class="border-b border-line bg-surface-muted"
      aria-labelledby="hero-title"
    >
      <div class="mx-auto grid max-w-6xl grid-cols-1 items-center gap-12 px-4 py-14 sm:px-6 sm:py-20 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]">
        <div class="flex flex-col items-start gap-6">
          <UiBadge tone="primary">
            {{ t('landing.hero.eyebrow') }}
          </UiBadge>
          <h1
            id="hero-title"
            class="text-4xl leading-tight font-bold text-balance text-fg sm:text-5xl"
          >
            {{ t('landing.hero.title') }}
          </h1>
          <p class="max-w-xl text-lg text-fg-muted">
            {{ t('landing.hero.subtitle') }}
          </p>
          <div class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row">
            <UiButton
              to="/auth/register"
              size="lg"
              :icon-end="forwardArrow"
            >
              {{ t('landing.hero.primary_cta') }}
            </UiButton>
            <UiButton
              to="/auth/login"
              size="lg"
              variant="secondary"
            >
              {{ t('landing.hero.secondary_cta') }}
            </UiButton>
          </div>
          <ul class="flex flex-wrap gap-x-5 gap-y-2 text-sm text-fg-muted">
            <li
              v-for="point in ['trial', 'arabic_english', 'web_mobile']"
              :key="point"
              class="inline-flex items-center gap-1.5"
            >
              <CircleCheck
                :size="16"
                class="text-brand"
                aria-hidden="true"
              />
              {{ t(`landing.hero.points.${point}`) }}
            </li>
          </ul>
        </div>

        <!-- Product preview -->
        <UiCard
          as="article"
          padding="none"
          class="shadow-lg"
          :aria-label="t('landing.preview.label')"
        >
          <div class="flex flex-col gap-5 p-5 sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-2">
              <CompetitionsDirectionChip direction="tender" />
              <CompetitionsStatusChip
                status="live"
                phase="open"
                :overlays="false"
              />
            </div>
            <div>
              <p class="text-lg font-bold text-fg">
                {{ t('landing.preview.title') }}
              </p>
              <p class="mt-1 text-sm text-fg-muted">
                {{ t('landing.preview.issuer') }}
              </p>
            </div>
            <dl class="grid grid-cols-1 gap-4 rounded-md bg-surface-muted p-4 sm:grid-cols-2">
              <div>
                <dt class="text-xs text-fg-muted">
                  {{ t('glossary.leading_offer') }}
                </dt>
                <dd class="mt-1 text-lg font-bold whitespace-nowrap text-fg tabular-nums sm:text-xl">
                  <bdi>{{ money.format(1_248_000_00) }}</bdi>
                </dd>
              </div>
              <div>
                <dt class="text-xs text-fg-muted">
                  {{ t('landing.preview.time_left') }}
                </dt>
                <dd class="mt-1 text-lg sm:text-xl">
                  <UiCountdown :ends-at="previewEndsAt" />
                </dd>
              </div>
            </dl>
            <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
              <span class="inline-flex items-center gap-2 text-fg-muted">
                <Users
                  :size="16"
                  aria-hidden="true"
                />
                {{ t('landing.preview.participants', { count: 7 }, 7) }}
              </span>
              <span class="inline-flex items-center gap-1.5 rounded-full bg-primary-soft px-2.5 py-1 font-semibold text-primary-soft-fg">
                <CircleCheck
                  :size="14"
                  aria-hidden="true"
                />
                {{ t('landing.preview.leading') }}
              </span>
            </div>
          </div>
        </UiCard>
      </div>
    </section>

    <!-- How it works -->
    <section
      id="how-it-works"
      class="mx-auto max-w-6xl px-4 py-16 sm:px-6"
      aria-labelledby="how-title"
    >
      <div class="max-w-2xl">
        <h2
          id="how-title"
          class="text-3xl font-bold text-fg"
        >
          {{ t('landing.how.title') }}
        </h2>
        <p class="mt-3 text-fg-muted">
          {{ t('landing.how.subtitle') }}
        </p>
      </div>
      <div class="mt-8">
        <UiTabs
          v-model="audience"
          :items="audienceTabs"
          :label="t('landing.how.tabs.label')"
        >
          <template #default="{ active }">
            <ol class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
              <li
                v-for="step in steps(active as Audience)"
                :key="step.key"
                class="flex flex-col gap-3 rounded-lg border border-line bg-surface p-5"
              >
                <div class="flex items-center gap-3">
                  <span class="inline-flex size-9 items-center justify-center rounded-full bg-primary text-sm font-bold text-primary-fg tabular-nums">
                    {{ step.number }}
                  </span>
                  <component
                    :is="step.icon"
                    :size="20"
                    class="text-brand"
                    aria-hidden="true"
                  />
                </div>
                <h3 class="text-lg font-bold text-fg">
                  {{ step.title }}
                </h3>
                <p class="text-sm text-fg-muted">
                  {{ step.body }}
                </p>
              </li>
            </ol>
          </template>
        </UiTabs>
      </div>
    </section>

    <!-- Tender and auction -->
    <section
      class="border-y border-line bg-surface-muted"
      aria-labelledby="modes-title"
    >
      <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
        <div class="max-w-2xl">
          <h2
            id="modes-title"
            class="text-3xl font-bold text-fg"
          >
            {{ t('landing.modes.title') }}
          </h2>
          <p class="mt-3 text-fg-muted">
            {{ t('landing.modes.subtitle') }}
          </p>
        </div>
        <div class="mt-10 grid gap-5 md:grid-cols-2">
          <UiCard
            v-for="direction in (['tender', 'auction'] as const)"
            :key="direction"
            as="article"
          >
            <div class="flex flex-col gap-4">
              <CompetitionsDirectionChip
                :direction="direction"
                class="self-start"
              />
              <h3 class="text-xl font-bold text-fg">
                {{ t(`landing.modes.${direction}.title`) }}
              </h3>
              <p class="text-fg-muted">
                {{ t(`landing.modes.${direction}.body`) }}
              </p>
              <p class="text-sm text-fg">
                <span class="font-semibold">{{ t('landing.modes.examples') }}</span>
                {{ t(`landing.modes.${direction}.examples`) }}
              </p>
            </div>
          </UiCard>
        </div>
        <div class="mt-5 grid gap-5 md:grid-cols-2">
          <div
            v-for="format in (['live', 'sealed'] as const)"
            :key="format"
            class="flex flex-col gap-3 rounded-lg border border-line bg-surface p-5"
          >
            <CompetitionsFormatChip
              :format="format"
              class="self-start"
            />
            <p class="text-sm text-fg-muted">
              {{ t(`landing.modes.formats.${format}`) }}
            </p>
          </div>
        </div>
        <p class="mt-6 flex items-start gap-2 text-sm text-fg-muted">
          <Gavel
            :size="18"
            class="mt-0.5 shrink-0 text-brand"
            aria-hidden="true"
          />
          {{ t('landing.modes.bafo_round') }}
        </p>
      </div>
    </section>

    <!-- Sponsored participation -->
    <section
      class="mx-auto max-w-6xl px-4 py-16 sm:px-6"
      aria-labelledby="sponsored-title"
    >
      <div class="grid items-center gap-10 lg:grid-cols-2">
        <div class="flex flex-col gap-4">
          <InvitationsFeesCoveredBadge class="self-start" />
          <h2
            id="sponsored-title"
            class="text-3xl font-bold text-fg"
          >
            {{ t('landing.sponsored.title') }}
          </h2>
          <p class="text-fg-muted">
            {{ t('landing.sponsored.body') }}
          </p>
        </div>
        <ul class="flex flex-col gap-4">
          <li
            v-for="point in ['choose', 'badge', 'unused']"
            :key="point"
            class="flex items-start gap-3 rounded-lg border border-line bg-surface p-5"
          >
            <span
              class="inline-flex size-10 shrink-0 items-center justify-center rounded-md bg-info-soft text-info-soft-fg"
              aria-hidden="true"
            >
              <Ticket :size="20" />
            </span>
            <div>
              <h3 class="font-bold text-fg">
                {{ t(`landing.sponsored.points.${point}.title`) }}
              </h3>
              <p class="mt-1 text-sm text-fg-muted">
                {{ t(`landing.sponsored.points.${point}.body`) }}
              </p>
            </div>
          </li>
        </ul>
      </div>
    </section>

    <!-- ERP connectivity -->
    <section
      class="border-y border-line bg-surface-muted"
      aria-labelledby="erp-title"
    >
      <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
          <div class="max-w-2xl">
            <h2
              id="erp-title"
              class="text-3xl font-bold text-fg"
            >
              {{ t('landing.erp.title') }}
            </h2>
            <p class="mt-3 text-fg-muted">
              {{ t('landing.erp.body') }}
            </p>
          </div>
          <UiButton
            variant="secondary"
            :href="`${apiOrigin}/docs/api`"
            :icon="BookOpen"
          >
            {{ t('landing.erp.docs') }}
          </UiButton>
        </div>
        <ul class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
          <li
            v-for="item in [{ key: 'api', icon: KeyRound }, { key: 'webhooks', icon: Webhook }, { key: 'files', icon: FileSpreadsheet }]"
            :key="item.key"
            class="flex flex-col gap-3 rounded-lg border border-line bg-surface p-5"
          >
            <span
              class="inline-flex size-10 items-center justify-center rounded-md bg-primary-soft text-primary-soft-fg"
              aria-hidden="true"
            >
              <component
                :is="item.icon"
                :size="20"
              />
            </span>
            <h3 class="text-lg font-bold text-fg">
              {{ t(`landing.erp.items.${item.key}.title`) }}
            </h3>
            <p class="text-sm text-fg-muted">
              {{ t(`landing.erp.items.${item.key}.body`) }}
            </p>
          </li>
        </ul>
      </div>
    </section>

    <!-- Trust -->
    <section
      class="mx-auto max-w-6xl px-4 py-16 sm:px-6"
      aria-labelledby="trust-title"
    >
      <div class="flex max-w-2xl items-start gap-3">
        <ShieldCheck
          :size="28"
          class="mt-1 shrink-0 text-brand"
          aria-hidden="true"
        />
        <div>
          <h2
            id="trust-title"
            class="text-3xl font-bold text-fg"
          >
            {{ t('landing.trust.title') }}
          </h2>
          <p class="mt-3 text-fg-muted">
            {{ t('landing.trust.subtitle') }}
          </p>
        </div>
      </div>
      <ul class="mt-10 grid gap-x-8 gap-y-10 sm:grid-cols-2 lg:grid-cols-3">
        <li
          v-for="item in trust"
          :key="item.key"
          class="flex flex-col gap-3"
        >
          <span
            class="inline-flex size-10 items-center justify-center rounded-md bg-primary-soft text-primary-soft-fg"
            aria-hidden="true"
          >
            <component
              :is="item.icon"
              :size="20"
            />
          </span>
          <h3 class="text-lg font-bold text-fg">
            {{ item.title }}
          </h3>
          <p class="text-fg-muted">
            {{ item.body }}
          </p>
        </li>
      </ul>
    </section>

    <!-- Plans teaser: hidden when the plans cannot be loaded -->
    <section
      v-if="teaserPlans.length > 0"
      id="plans"
      class="border-y border-line bg-surface-muted"
      aria-labelledby="plans-title"
    >
      <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
        <div class="max-w-2xl">
          <h2
            id="plans-title"
            class="text-3xl font-bold text-fg"
          >
            {{ t('landing.plans.title') }}
          </h2>
          <p class="mt-3 text-fg-muted">
            {{ t('landing.plans.subtitle') }}
          </p>
        </div>
        <ul class="mt-10 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
          <li
            v-for="plan in teaserPlans"
            :key="plan.id"
          >
            <BillingPlanCard
              :plan="plan"
              interval="monthly"
            >
              <template #action>
                <UiButton
                  block
                  :variant="plan.is_featured ? 'primary' : 'secondary'"
                  to="/auth/register"
                >
                  {{ t('landing.plans.cta') }}
                </UiButton>
              </template>
            </BillingPlanCard>
          </li>
        </ul>
        <div class="mt-6 flex flex-col gap-1 text-sm text-fg-muted">
          <p v-if="hasCustomPlan">
            {{ t('landing.plans.custom') }}
          </p>
          <p>{{ t('landing.plans.trial') }}</p>
          <p>{{ t('common.prices_exclude_vat') }}</p>
        </div>
      </div>
    </section>

    <!-- FAQ -->
    <section
      id="faq"
      class="mx-auto max-w-3xl px-4 py-16 sm:px-6"
      aria-labelledby="faq-title"
    >
      <h2
        id="faq-title"
        class="text-3xl font-bold text-fg"
      >
        {{ t('landing.faq.title') }}
      </h2>
      <div class="mt-8 flex flex-col divide-y divide-line rounded-lg border border-line bg-surface">
        <details
          v-for="item in faq"
          :key="item.key"
          class="group px-5 py-4"
        >
          <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-4 font-semibold text-fg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring [&::-webkit-details-marker]:hidden">
            {{ item.question }}
            <ChevronDown
              :size="20"
              class="shrink-0 text-fg-muted transition-transform group-open:rotate-180 motion-reduce:transition-none"
              aria-hidden="true"
            />
          </summary>
          <p class="mt-2 text-fg-muted">
            {{ item.answer }}
          </p>
        </details>
      </div>
    </section>

    <!-- Contact -->
    <section
      id="contact"
      class="border-t border-line bg-surface-muted"
      aria-labelledby="contact-title"
    >
      <div class="mx-auto grid max-w-6xl gap-10 px-4 py-16 sm:px-6 lg:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)]">
        <div class="flex flex-col gap-3">
          <h2
            id="contact-title"
            class="text-3xl font-bold text-fg"
          >
            {{ t('landing.contact.title') }}
          </h2>
          <p class="text-fg-muted">
            {{ t('landing.contact.body') }}
          </p>
        </div>
        <UiCard>
          <div
            v-if="contactSent"
            class="flex flex-col items-center gap-3 py-6 text-center"
            role="status"
          >
            <span
              class="inline-flex size-12 items-center justify-center rounded-full bg-primary-soft text-primary-soft-fg"
              aria-hidden="true"
            >
              <CircleCheck :size="24" />
            </span>
            <p class="text-lg font-bold text-fg">
              {{ t('landing.contact.sent_title') }}
            </p>
            <p class="text-fg-muted">
              {{ t('landing.contact.sent_body') }}
            </p>
          </div>
          <form
            v-else
            class="relative flex flex-col gap-4"
            novalidate
            @submit.prevent="sendContact"
          >
            <UiAlert
              v-if="contactError"
              tone="danger"
              role="alert"
            >
              {{ contactError }}
            </UiAlert>
            <div class="grid gap-4 sm:grid-cols-2">
              <UiInput
                v-model="contact.name"
                :label="t('landing.contact.fields.name')"
                :error="contactFieldError('name')"
                autocomplete="name"
                :maxlength="150"
                required
              />
              <UiInput
                v-model="contact.email"
                type="email"
                inputmode="email"
                dir="ltr"
                :label="t('landing.contact.fields.email')"
                :error="contactFieldError('email')"
                autocomplete="email"
                :maxlength="255"
                required
              />
              <UiPhoneInput
                v-model="contact.phone"
                :label="t('landing.contact.fields.phone')"
                :error="contactFieldError('phone')"
              />
              <UiInput
                v-model="contact.company"
                :label="t('landing.contact.fields.company')"
                :error="contactFieldError('company')"
                autocomplete="organization"
                :maxlength="150"
              />
            </div>
            <UiInput
              v-model="contact.subject"
              :label="t('landing.contact.fields.subject')"
              :error="contactFieldError('subject')"
              :maxlength="150"
              required
            />
            <UiTextarea
              v-model="contact.message"
              :label="t('landing.contact.fields.message')"
              :error="contactFieldError('message')"
              :rows="5"
              :maxlength="5000"
              required
            />
            <AppHoneypot v-model="contact.website_url" />
            <div class="flex justify-end">
              <UiButton
                type="submit"
                :loading="contactBusy"
                :icon="Send"
                flip-icons
              >
                {{ t('landing.contact.submit') }}
              </UiButton>
            </div>
          </form>
        </UiCard>
      </div>
    </section>

    <!-- Closing call to action -->
    <section class="bg-charcoal text-white">
      <div class="mx-auto flex max-w-6xl flex-col items-start gap-6 px-4 py-16 sm:px-6 md:flex-row md:items-center md:justify-between">
        <div>
          <p class="text-3xl">
            <AppTagline inverse />
          </p>
          <p class="mt-2 text-gray-300">
            {{ t('landing.cta.body') }}
          </p>
        </div>
        <UiButton
          to="/auth/register"
          size="lg"
          :icon-end="forwardArrow"
        >
          {{ t('landing.hero.primary_cta') }}
        </UiButton>
      </div>
    </section>
  </div>
</template>
