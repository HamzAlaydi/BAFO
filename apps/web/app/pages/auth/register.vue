<script setup lang="ts">
import { ArrowLeft, ArrowRight, Info } from '@lucide/vue'
import { register } from '~/services/identity'
import type { RegisterRequest } from '~/types/api/identity'
import type { StepItem } from '~/types/ui'

/**
 * W05 Register · `/auth/register` (SCREENS §2.4): one form in four sections, shown as a stepper on
 * narrow screens: contact person, company (CR, region, city, VAT), national address and activity
 * (optional), consent. `POST /auth/register` → W06 with `?email=`. The pending competition
 * invitation token (CD7) is sent as `invitation_token`.
 */
definePageMeta({ layout: 'auth', middleware: 'guest', authWide: true })

const { t } = useI18n()
const locale = useAppLocale()
const localePath = useLocalePath()
const pendingInvitation = usePendingToken('invitation')
const pendingInvitationEmail = useState<string | null>('bafo:pending-invitation-email', () => null)
const otpHandoff = useOtpHandoff('email_verification')
const cooldown = useCooldown()
const { message } = useErrorMessage()
// The stepper applies after mount only, so the server render and hydration always show the full form.
const mounted = useMounted()
const narrowQuery = useMediaQuery('(max-width: 639px)')
const narrow = computed(() => mounted.value && narrowQuery.value)

useSeoMeta({ title: () => t('auth.register.title') })

type SectionKey = 'contact' | 'company' | 'address' | 'consent'
const SECTIONS: SectionKey[] = ['contact', 'company', 'address', 'consent']
const steps = computed<StepItem[]>(() => SECTIONS.map(key => ({ key, label: t(`auth.register.sections.${key}.title`) })))
const current = ref(0)
const currentKey = computed<SectionKey>(() => SECTIONS[current.value] ?? 'contact')
const showSection = (key: SectionKey) => !narrow.value || currentKey.value === key

const form = reactive({
  name: '',
  email: '',
  phone: null as string | null,
  password: '',
  password_confirmation: '',
  locale: locale.value as 'ar' | 'en',
  organization: {
    name: '',
    cr_number: '',
    region_id: null as string | null,
    city: '',
    vat_registered: false,
    vat_number: '',
    legal_name_ar: '',
    legal_name_en: '',
    website: '',
    national_address: emptyNationalAddress(),
    category_ids: [] as string[],
    visible_in_suggestions: true,
  },
  accept_terms: false,
  accept_privacy: false,
  website_url: '',
})

// Leaving a started registration asks first (SCREENS S7); a completed one leaves freely.
const registered = ref(false)
const started = computed(() => form.phone !== null || [form.name, form.email, form.password, form.organization.name, form.organization.cr_number, form.organization.city].some(value => value.trim() !== ''))
useUnsavedChangesGuard(() => started.value && !registered.value)

const errors = ref<Record<string, string | undefined>>({})
const serverErrorFields = ref<Set<string>>(new Set())
const formError = ref<string | null>(null)
/** After a submit attempt, every field error is listed at the top with a link to its field (FQ8). */
const submitted = ref(false)
const errorSummary = computed(() => (submitted.value
  ? Object.entries(errors.value).filter((entry): entry is [string, string] => Boolean(entry[1])).map(([field, text]) => ({ field, text }))
  : []))
const summaryEl = useTemplateRef<HTMLElement>('summary')

async function focusField(field: string): Promise<void> {
  const section = SECTION_OF_FIELD[field]
  if (section === 'address') addressOpen.value = true
  if (narrow.value && section) current.value = SECTIONS.indexOf(section)
  await nextTick()
  const control = document.querySelector<HTMLElement>(`[data-field="${field}"] input, [data-field="${field}"] select, [data-field="${field}"] textarea, [data-field="${field}"] [role="checkbox"], [data-field="${field}"] button`)
  const target = control ?? document.querySelector<HTMLElement>(`[data-field="${field}"]`)
  target?.scrollIntoView({ block: 'center' })
  target?.focus()
}
const invitationNotice = ref<string | null>(null)
const submitting = ref(false)
const addressOpen = ref(false)
const hasInvitation = ref(false)

onMounted(() => {
  hasInvitation.value = Boolean(pendingInvitation.read())
})

const SECTION_OF_FIELD: Record<string, SectionKey> = {
  'name': 'contact',
  'email': 'contact',
  'phone': 'contact',
  'password': 'contact',
  'password_confirmation': 'contact',
  'locale': 'contact',
  'organization.name': 'company',
  'organization.cr_number': 'company',
  'organization.region_id': 'company',
  'organization.city': 'company',
  'organization.vat_registered': 'company',
  'organization.vat_number': 'company',
  'organization.legal_name_ar': 'company',
  'organization.legal_name_en': 'company',
  'organization.website': 'company',
  'organization.national_address.building_number': 'address',
  'organization.national_address.street': 'address',
  'organization.national_address.district': 'address',
  'organization.national_address.postal_code': 'address',
  'organization.national_address.additional_number': 'address',
  'organization.national_address.short_address': 'address',
  'organization.category_ids': 'address',
  'organization.visible_in_suggestions': 'address',
  'accept_terms': 'consent',
  'accept_privacy': 'consent',
}
const FIELDS = Object.keys(SECTION_OF_FIELD)

const required = (value: string | null | undefined) => (!value || value.trim() === '' ? t('validation.required') : undefined)
const maxLength = (value: string, max: number) => (value.trim().length > max ? t('validation.max_length', { max }) : undefined)

function validateSection(key: SectionKey): Record<string, string | undefined> {
  const org = form.organization
  if (key === 'contact') {
    return {
      name: required(form.name) ?? maxLength(form.name, 150),
      email: required(form.email) ?? (isEmail(form.email) ? undefined : t('validation.email')),
      phone: form.phone ? undefined : t('validation.mobile'),
      password: required(form.password) ?? (isStrongPassword(form.password) ? undefined : t('validation.password')),
      password_confirmation: required(form.password_confirmation)
        ?? (form.password_confirmation === form.password ? undefined : t('validation.password_confirmation')),
    }
  }
  if (key === 'company') {
    return {
      'organization.name': required(org.name) ?? maxLength(org.name, 150),
      'organization.cr_number': required(org.cr_number) ?? (isSaudiCrNumber(org.cr_number) ? undefined : t('validation.cr_number')),
      'organization.region_id': org.region_id ? undefined : t('validation.required'),
      'organization.city': required(org.city) ?? maxLength(org.city, 100),
      'organization.vat_number': org.vat_registered
        ? required(org.vat_number) ?? (isSaudiVatNumber(org.vat_number) ? undefined : t('validation.vat_number'))
        : undefined,
      'organization.legal_name_ar': maxLength(org.legal_name_ar, 200),
      'organization.legal_name_en': maxLength(org.legal_name_en, 200),
      'organization.website': org.website.trim() && !isHttpsUrl(org.website) ? t('validation.url_https') : undefined,
    }
  }
  if (key === 'address') {
    const formatErrors = nationalAddressFormatErrors(org.national_address)
    const result: Record<string, string | undefined> = {}
    for (const [part, messageKey] of Object.entries(formatErrors)) {
      result[`organization.national_address.${part}`] = t(messageKey)
    }
    result['organization.national_address.street'] = maxLength(org.national_address.street, 150)
    result['organization.national_address.district'] = maxLength(org.national_address.district, 150)
    if (org.category_ids.length > MAX_CATEGORIES) result['organization.category_ids'] = t('validation.categories_max', { max: MAX_CATEGORIES })
    return result
  }
  return {
    accept_terms: form.accept_terms ? undefined : t('validation.accept_terms'),
    accept_privacy: form.accept_privacy ? undefined : t('validation.accept_privacy'),
  }
}

function applyErrors(next: Record<string, string | undefined>, sections: SectionKey[]): boolean {
  const kept = Object.fromEntries(Object.entries(errors.value).filter(([field]) => !sections.includes(SECTION_OF_FIELD[field] ?? 'contact')))
  errors.value = { ...kept, ...next }
  return Object.values(next).every(value => !value)
}

function firstInvalidSection(): SectionKey | null {
  for (const section of SECTIONS) {
    if (Object.entries(errors.value).some(([field, value]) => value && SECTION_OF_FIELD[field] === section)) return section
  }
  return null
}

async function focusFirstError(): Promise<void> {
  const section = firstInvalidSection()
  if (section === null) return
  if (section === 'address') addressOpen.value = true
  if (narrow.value) current.value = SECTIONS.indexOf(section)
  await nextTick()
  document.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus()
}

function next(): void {
  if (applyErrors(validateSection(currentKey.value), [currentKey.value])) {
    current.value = Math.min(current.value + 1, SECTIONS.length - 1)
    if (currentKey.value === 'address') addressOpen.value = true
    window.scrollTo({ top: 0 })
  }
  else {
    void focusFirstError()
  }
}

function back(): void {
  current.value = Math.max(current.value - 1, 0)
}

function payload(): RegisterRequest {
  const org = form.organization
  return {
    name: form.name.trim(),
    email: form.email.trim(),
    phone: form.phone ?? '',
    password: form.password,
    password_confirmation: form.password_confirmation,
    locale: form.locale,
    organization: {
      name: org.name.trim(),
      cr_number: normalizeDigits(org.cr_number).trim(),
      region_id: org.region_id ?? '',
      city: org.city.trim(),
      vat_registered: org.vat_registered,
      vat_number: org.vat_registered ? normalizeDigits(org.vat_number).trim() : null,
      legal_name_ar: optionalText(org.legal_name_ar),
      legal_name_en: optionalText(org.legal_name_en),
      website: optionalText(org.website),
      national_address: nationalAddressPayload(org.national_address),
      category_ids: org.category_ids,
      visible_in_suggestions: org.visible_in_suggestions,
    },
    accept_terms: form.accept_terms,
    accept_privacy: form.accept_privacy,
    invitation_token: pendingInvitation.read(),
    website_url: form.website_url,
  }
}

async function submit(): Promise<void> {
  formError.value = null
  submitted.value = true
  const allValid = SECTIONS.map(section => applyErrors(validateSection(section), [section])).every(Boolean)
  if (!allValid) {
    await nextTick()
    summaryEl.value?.focus()
    return
  }
  if (cooldown.active.value) return
  submitting.value = true
  try {
    const result = await register(payload())
    otpHandoff.set(result.email, result.otp_expires_at)
    registered.value = true
    await navigateTo(localePath({ path: '/auth/verify', query: { email: result.email } }))
  }
  catch (error) {
    await handleError(error)
  }
  finally {
    submitting.value = false
  }
}

async function handleError(error: unknown): Promise<void> {
  if (!(error instanceof ApiError)) {
    formError.value = t('errors.unknown')
    return
  }
  if (error.code === 'invitation_email_mismatch') {
    errors.value = { ...errors.value, email: pendingInvitationEmail.value
      ? t('auth.register.invitation_email_mismatch', { email: pendingInvitationEmail.value })
      : message(error) }
    await focusFirstError()
    return
  }
  if (error.code === 'too_many_requests') cooldown.start(error.retryAfterSeconds ?? 60)
  if (error.isValidation) {
    if (error.errors.invitation_token) {
      // Unknown or expired invitation: register without it and explain (SCREENS W05).
      pendingInvitation.clear()
      hasInvitation.value = false
      invitationNotice.value = t('auth.register.invitation_dropped')
    }
    const bound = bindFieldErrors(error, FIELDS)
    serverErrorFields.value = new Set(Object.keys(bound.fields))
    errors.value = { ...errors.value, ...bound.fields }
    const unmatched = bound.unmatched.filter(text => !error.errors.invitation_token?.includes(text))
    formError.value = unmatched[0] ?? (Object.keys(bound.fields).length === 0 && !error.errors.invitation_token ? message(error) : null)
    await focusFirstError()
    return
  }
  formError.value = message(error)
}

const forwardIcon = computed(() => (locale.value === 'ar' ? ArrowLeft : ArrowRight))
const backIcon = computed(() => (locale.value === 'ar' ? ArrowRight : ArrowLeft))
const localeOptions = computed(() => [
  { value: 'ar' as const, label: t('common.languages.ar') },
  { value: 'en' as const, label: t('common.languages.en') },
])
</script>

<template>
  <div class="flex flex-col gap-8">
    <div>
      <h1 class="text-2xl font-bold text-fg sm:text-3xl">
        {{ t('auth.register.title') }}
      </h1>
      <p class="mt-2 text-fg-muted">
        {{ t('auth.register.subtitle') }}
      </p>
    </div>

    <UiAlert
      v-if="hasInvitation"
      tone="info"
      :icon="Info"
    >
      {{ pendingInvitationEmail ? t('auth.register.invitation_hint_masked', { email: pendingInvitationEmail }) : t('auth.register.invitation_hint') }}
    </UiAlert>
    <UiAlert
      v-if="invitationNotice"
      tone="warning"
      dismissible
      @dismiss="invitationNotice = null"
    >
      {{ invitationNotice }}
    </UiAlert>

    <UiStepper
      v-if="narrow"
      :steps="steps"
      :current="current"
      navigable
      @select="current = $event"
    />

    <UiAlert
      v-if="formError"
      tone="danger"
      dismissible
      @dismiss="formError = null"
    >
      {{ formError }}
    </UiAlert>

    <div
      v-if="errorSummary.length > 0"
      ref="summary"
      tabindex="-1"
      class="outline-none"
    >
      <UiAlert
        tone="danger"
        :title="t('auth.register.error_summary')"
      >
        <ul class="mt-1 flex flex-col gap-1">
          <li
            v-for="item in errorSummary"
            :key="item.field"
          >
            <a
              href="#"
              class="link"
              @click.prevent="focusField(item.field)"
            >{{ item.text }}</a>
          </li>
        </ul>
      </UiAlert>
    </div>

    <form
      class="relative flex flex-col gap-8"
      novalidate
      @submit.prevent="narrow && currentKey !== 'consent' ? next() : submit()"
    >
      <AppHoneypot v-model="form.website_url" />

      <!-- 1. Contact person -->
      <section
        v-show="showSection('contact')"
        class="flex flex-col gap-5"
        aria-labelledby="register-contact"
      >
        <h2
          id="register-contact"
          class="text-lg font-bold text-fg"
        >
          {{ t('auth.register.sections.contact.title') }}
        </h2>
        <UiInput
          v-model="form.name"
          :label="t('auth.fields.name')"
          autocomplete="name"
          :maxlength="150"
          required
          :error="errors.name"
          data-field="name"
        />
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
          <div class="flex flex-col gap-1.5">
            <UiInput
              v-model="form.email"
              :label="t('auth.fields.email')"
              type="email"
              autocomplete="email"
              inputmode="email"
              dir="ltr"
              required
              :error="errors.email"
              data-field="email"
            />
            <NuxtLinkLocale
              v-if="serverErrorFields.has('email')"
              to="/auth/login"
              class="link text-sm"
            >
              {{ t('auth.register.sign_in_instead') }}
            </NuxtLinkLocale>
          </div>
          <div data-field="phone">
            <UiPhoneInput
              v-model="form.phone"
              :label="t('auth.fields.phone')"
              :hint="t('auth.fields.phone_hint')"
              required
              :error="errors.phone"
            />
          </div>
        </div>
        <div data-field="password">
          <UiPasswordInput
            v-model="form.password"
            :label="t('auth.fields.password')"
            autocomplete="new-password"
            checklist
            required
            :error="errors.password"
          />
        </div>
        <div data-field="password_confirmation">
          <UiPasswordInput
            v-model="form.password_confirmation"
            :label="t('auth.fields.password_confirmation')"
            autocomplete="new-password"
            required
            :error="errors.password_confirmation"
          />
        </div>
        <UiSegmented
          v-model="form.locale"
          :options="localeOptions"
          :label="t('auth.fields.locale')"
          :hint="t('auth.fields.locale_hint')"
        />
      </section>

      <!-- 2. Company -->
      <section
        v-show="showSection('company')"
        class="flex flex-col gap-5"
        aria-labelledby="register-company"
      >
        <h2
          id="register-company"
          class="text-lg font-bold text-fg"
        >
          {{ t('auth.register.sections.company.title') }}
        </h2>
        <UiInput
          v-model="form.organization.name"
          :label="t('organization.fields.name')"
          autocomplete="organization"
          :maxlength="150"
          required
          :error="errors['organization.name']"
          data-field="organization.name"
        />
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
          <div class="flex flex-col gap-1.5">
            <UiInput
              v-model="form.organization.cr_number"
              :label="t('organization.fields.cr_number')"
              :hint="t('organization.fields.cr_number_hint')"
              inputmode="numeric"
              dir="ltr"
              :maxlength="10"
              required
              :error="errors['organization.cr_number']"
              data-field="organization.cr_number"
            />
            <NuxtLinkLocale
              v-if="serverErrorFields.has('organization.cr_number')"
              to="/auth/login"
              class="link text-sm"
            >
              {{ t('auth.register.sign_in_instead') }}
            </NuxtLinkLocale>
          </div>
          <div data-field="organization.region_id">
            <OrganizationRegionSelect
              v-model="form.organization.region_id"
              :label="t('organization.fields.region')"
              required
              :error="errors['organization.region_id']"
            />
          </div>
        </div>
        <UiInput
          v-model="form.organization.city"
          :label="t('organization.fields.city')"
          autocomplete="address-level2"
          :maxlength="100"
          required
          :error="errors['organization.city']"
          data-field="organization.city"
        />
        <UiSwitch
          v-model="form.organization.vat_registered"
          :label="t('organization.fields.vat_registered')"
          :description="t('organization.fields.vat_registered_hint')"
        />
        <UiInput
          v-if="form.organization.vat_registered"
          v-model="form.organization.vat_number"
          :label="t('organization.fields.vat_number')"
          :hint="t('organization.fields.vat_number_hint')"
          inputmode="numeric"
          dir="ltr"
          :maxlength="15"
          required
          :error="errors['organization.vat_number']"
          data-field="organization.vat_number"
        />
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
          <UiInput
            v-model="form.organization.legal_name_ar"
            :label="t('organization.fields.legal_name_ar')"
            :hint="t('organization.fields.legal_name_hint')"
            dir="rtl"
            lang="ar"
            :maxlength="200"
            :error="errors['organization.legal_name_ar']"
          />
          <UiInput
            v-model="form.organization.legal_name_en"
            :label="t('organization.fields.legal_name_en')"
            :hint="t('organization.fields.legal_name_hint')"
            dir="ltr"
            lang="en"
            :maxlength="200"
            :error="errors['organization.legal_name_en']"
          />
        </div>
        <UiInput
          v-model="form.organization.website"
          :label="t('organization.fields.website')"
          :hint="t('organization.fields.website_hint')"
          type="url"
          inputmode="url"
          autocomplete="url"
          dir="ltr"
          :error="errors['organization.website']"
        />
      </section>

      <!-- 3. National address and activity (optional) -->
      <section
        v-show="showSection('address')"
        class="flex flex-col gap-5"
        aria-labelledby="register-address"
      >
        <div class="flex flex-col gap-1">
          <h2
            id="register-address"
            class="text-lg font-bold text-fg"
          >
            {{ t('auth.register.sections.address.title') }}
          </h2>
          <p class="text-sm text-fg-muted">
            {{ t('auth.register.sections.address.description') }}
          </p>
        </div>
        <details
          class="group rounded-lg border border-line bg-surface"
          :open="addressOpen || narrow"
          @toggle="addressOpen = ($event.target as HTMLDetailsElement).open"
        >
          <summary class="flex cursor-pointer items-center justify-between gap-3 rounded-lg px-4 py-3 font-semibold text-fg focus-visible:outline-2 focus-visible:outline-ring">
            {{ t('organization.sections.national_address') }}
            <span class="text-sm font-normal text-fg-muted">{{ t('common.optional') }}</span>
          </summary>
          <div class="border-t border-line p-4">
            <OrganizationNationalAddressFields
              v-model="form.organization.national_address"
              :errors="{
                building_number: errors['organization.national_address.building_number'],
                street: errors['organization.national_address.street'],
                district: errors['organization.national_address.district'],
                postal_code: errors['organization.national_address.postal_code'],
                additional_number: errors['organization.national_address.additional_number'],
                short_address: errors['organization.national_address.short_address'],
              }"
            />
          </div>
        </details>
        <OrganizationCategoryPicker
          v-model="form.organization.category_ids"
          :label="t('organization.fields.categories')"
          :hint="t('organization.fields.categories_hint')"
          :error="errors['organization.category_ids']"
        />
        <UiSwitch
          v-model="form.organization.visible_in_suggestions"
          :label="t('organization.fields.visible_in_suggestions')"
          :description="t('organization.fields.visible_in_suggestions_hint')"
        />
      </section>

      <!-- 4. Consent -->
      <section
        v-show="showSection('consent')"
        class="flex flex-col gap-4"
        aria-labelledby="register-consent"
      >
        <h2
          id="register-consent"
          class="text-lg font-bold text-fg"
        >
          {{ t('auth.register.sections.consent.title') }}
        </h2>
        <div data-field="accept_terms">
          <UiCheckbox
            v-model="form.accept_terms"
            :label="t('auth.register.accept_terms')"
            required
            :error="errors.accept_terms"
          />
        </div>
        <div data-field="accept_privacy">
          <UiCheckbox
            v-model="form.accept_privacy"
            :label="t('auth.register.accept_privacy')"
            required
            :error="errors.accept_privacy"
          />
        </div>
        <p class="text-sm text-fg-muted">
          {{ t('auth.register.read_documents') }}
          <NuxtLinkLocale
            to="/legal/terms"
            target="_blank"
            class="link"
          >
            {{ t('legal.codes.terms') }}
          </NuxtLinkLocale>
          ·
          <NuxtLinkLocale
            to="/legal/privacy"
            target="_blank"
            class="link"
          >
            {{ t('legal.codes.privacy') }}
          </NuxtLinkLocale>
        </p>
      </section>

      <div
        v-if="narrow"
        class="flex flex-col-reverse gap-3 xs:flex-row xs:justify-between"
      >
        <UiButton
          v-if="current > 0"
          variant="secondary"
          :icon="backIcon"
          @click="back"
        >
          {{ t('common.actions.back') }}
        </UiButton>
        <span v-else />
        <UiButton
          type="submit"
          :loading="submitting"
          :disabled="cooldown.active.value"
          :icon-end="currentKey === 'consent' ? undefined : forwardIcon"
        >
          {{ currentKey === 'consent'
            ? (cooldown.active.value ? t('common.retry_in', { seconds: cooldown.seconds.value }) : t('auth.register.submit'))
            : t('common.actions.continue') }}
        </UiButton>
      </div>
      <UiButton
        v-else
        type="submit"
        size="lg"
        block
        :loading="submitting"
        :disabled="cooldown.active.value"
      >
        {{ cooldown.active.value ? t('common.retry_in', { seconds: cooldown.seconds.value }) : t('auth.register.submit') }}
      </UiButton>
    </form>

    <p class="text-center text-sm text-fg-muted">
      {{ t('auth.register.have_account') }}
      <NuxtLinkLocale
        to="/auth/login"
        class="link"
      >
        {{ t('auth.register.login_link') }}
      </NuxtLinkLocale>
    </p>
  </div>
</template>
