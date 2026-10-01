<script setup lang="ts">
import { BadgeCheck, Lock } from '@lucide/vue'
import { fetchOrganization, updateOrganization } from '~/services/identity'
import type { Organization, UpdateOrganizationRequest } from '~/types/api/identity'
import type { NationalAddressForm } from '~/utils/organization-form'

/**
 * W27 Organisation · `/dashboard/organization` (SCREENS §2.4). Everyone can read; editing needs
 * `organization.update`. Identity (logo, names, CR read-only, verified read-only), tax, location with
 * the national address, contact (e-mail and phone read-only), activity, company profile PDF and the
 * admin-controlled features (read-only). The billing profile banner lists the missing fields; with a
 * same-origin `?return=` the page goes back there once a save completes the profile.
 */
definePageMeta({ layout: 'dashboard', middleware: 'auth' })

const { t } = useI18n()
const route = useRoute()
const auth = useAuthStore()
const toast = useToast()
const { message, bind } = useErrorMessage()

useSeoMeta({ title: () => t('organization.title') })

const organization = ref<Organization | null>(null)
const loading = ref(true)
const loadError = ref<unknown>(null)
const saving = ref(false)
const errors = ref<Record<string, string | undefined>>({})
const formError = ref<string | null>(null)

const editable = computed(() => auth.can('organization.update'))
const returnTo = computed(() => {
  const target = safeRedirect(route.query.return, '')
  return /^\/(ar|en)\/dashboard(\/|$)/.test(target) ? target : null
})

const form = reactive({
  name: '',
  legal_name_ar: '',
  legal_name_en: '',
  vat_registered: false,
  vat_number: '',
  region_id: null as string | null,
  city: '',
  national_address: emptyNationalAddress() as NationalAddressForm,
  website: '',
  category_ids: [] as string[],
  visible_in_suggestions: true,
})
const initial = ref('')
const dirty = computed(() => organization.value !== null && JSON.stringify(form) !== initial.value)
useUnsavedChangesGuard(dirty)

function fill(next: Organization): void {
  organization.value = next
  form.name = next.name
  form.legal_name_ar = next.legal_name_ar ?? ''
  form.legal_name_en = next.legal_name_en ?? ''
  form.vat_registered = next.vat_registered
  form.vat_number = next.vat_number ?? ''
  form.region_id = next.region?.id ?? null
  form.city = next.city ?? ''
  form.national_address = nationalAddressFromApi(next.national_address)
  form.website = next.website ?? ''
  form.category_ids = next.categories.map(category => category.id)
  form.visible_in_suggestions = next.visible_in_suggestions
  initial.value = JSON.stringify(form)
}

/** Keeps the top bar (logo, name, features) in step with the saved organisation. */
function syncSession(next: Organization): void {
  if (auth.me) auth.applyMe({ ...auth.me, organization: next })
}

async function load(): Promise<void> {
  loading.value = true
  loadError.value = null
  try {
    fill(await fetchOrganization())
  }
  catch (error) {
    loadError.value = error
  }
  finally {
    loading.value = false
  }
}

onMounted(load)

const missing = computed(() => new Set(organization.value?.billing_profile_missing ?? []))
const hint = (path: string) => (missing.value.has(path) ? t('organization.billing_profile.required_hint') : undefined)
const missingAddressParts = computed(() => [...missing.value]
  .filter(path => path.startsWith('national_address.'))
  .map(path => path.replace('national_address.', '') as keyof NationalAddressForm))

const FIELDS = [
  'name', 'legal_name_ar', 'legal_name_en', 'vat_registered', 'vat_number', 'region_id', 'city', 'website',
  'category_ids', 'visible_in_suggestions', ...NATIONAL_ADDRESS_PARTS.map(part => `national_address.${part}`),
]

function validate(): boolean {
  const next: Record<string, string | undefined> = {
    name: !form.name.trim() ? t('validation.required') : form.name.trim().length > 150 ? t('validation.max_length', { max: 150 }) : undefined,
    region_id: form.region_id ? undefined : t('validation.required'),
    city: !form.city.trim() ? t('validation.required') : undefined,
    vat_number: form.vat_registered && !isSaudiVatNumber(form.vat_number) ? t('validation.vat_number') : undefined,
    website: form.website.trim() && !isHttpsUrl(form.website) ? t('validation.url_https') : undefined,
    category_ids: form.category_ids.length > MAX_CATEGORIES ? t('validation.categories_max', { max: MAX_CATEGORIES }) : undefined,
  }
  for (const [part, key] of Object.entries(nationalAddressFormatErrors(form.national_address))) {
    next[`national_address.${part}`] = t(key)
  }
  errors.value = next
  return Object.values(next).every(value => !value)
}

function payload(): UpdateOrganizationRequest {
  return {
    name: form.name.trim(),
    legal_name_ar: optionalText(form.legal_name_ar),
    legal_name_en: optionalText(form.legal_name_en),
    vat_registered: form.vat_registered,
    vat_number: form.vat_registered ? normalizeDigits(form.vat_number).trim() : null,
    region_id: form.region_id ?? undefined,
    city: form.city.trim(),
    national_address: nationalAddressPayload(form.national_address),
    website: optionalText(form.website),
    category_ids: form.category_ids,
    visible_in_suggestions: form.visible_in_suggestions,
  }
}

async function save(): Promise<void> {
  formError.value = null
  if (!validate()) {
    await nextTick()
    document.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus()
    return
  }
  saving.value = true
  try {
    const wasComplete = organization.value?.billing_profile_complete === true
    const saved = await updateOrganization(payload())
    fill(saved)
    syncSession(saved)
    toast.success(t('organization.saved'))
    if (!wasComplete && saved.billing_profile_complete && returnTo.value) await navigateTo(returnTo.value)
  }
  catch (error) {
    const bound = bind(error, FIELDS, { 'organization.region_id': 'region_id' })
    errors.value = { ...errors.value, ...bound.fields }
    formError.value = bound.unmatched[0] ?? (error instanceof ApiError && error.isValidation ? null : message(error))
  }
  finally {
    saving.value = false
  }
}

/** Uploads save immediately; unsaved field edits are kept. */
function onFileUpdated(next: Organization): void {
  syncSession(next)
  if (dirty.value) organization.value = next
  else fill(next)
}

const FEATURES = ['api_enabled', 'auction_enabled', 'sponsorship_enabled'] as const
</script>

<template>
  <div class="flex flex-col gap-6">
    <UiPageHeader
      :title="t('organization.title')"
      :description="editable ? t('organization.subtitle') : t('organization.read_only')"
    >
      <template
        v-if="organization"
        #meta
      >
        <p class="mt-2 flex flex-wrap items-center gap-2">
          <UiBadge
            v-if="organization.verified"
            tone="primary"
            :icon="BadgeCheck"
          >
            {{ t('organization.verified') }}
          </UiBadge>
          <UiBadge
            v-else
            tone="neutral"
          >
            {{ t('organization.not_verified') }}
          </UiBadge>
        </p>
      </template>
    </UiPageHeader>

    <div
      v-if="loading && !organization"
      class="flex flex-col gap-4"
      aria-busy="true"
      :aria-label="t('common.loading')"
    >
      <UiSkeleton
        v-for="n in 3"
        :key="n"
        class="h-40 w-full rounded-lg"
      />
    </div>

    <UiCard
      v-else-if="loadError && !organization"
      padding="none"
    >
      <UiErrorState
        :error="loadError"
        :retrying="loading"
        @retry="load"
      />
    </UiCard>

    <template v-else-if="organization">
      <OrganizationBillingProfileBanner
        v-if="!organization.billing_profile_complete"
        :missing="organization.billing_profile_missing"
      />

      <UiAlert
        v-if="formError"
        tone="danger"
        dismissible
        @dismiss="formError = null"
      >
        {{ formError }}
      </UiAlert>

      <form
        class="flex flex-col gap-6"
        novalidate
        @submit.prevent="save"
      >
        <UiCard :title="t('organization.sections.identity')">
          <div class="flex flex-col gap-5">
            <OrganizationLogoUploader
              :organization="organization"
              :editable="editable"
              @updated="onFileUpdated"
            />
            <UiInput
              v-model="form.name"
              :label="t('organization.fields.name')"
              :maxlength="150"
              required
              :readonly="!editable"
              :error="errors.name"
            />
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
              <UiInput
                v-model="form.legal_name_ar"
                :label="t('organization.fields.legal_name_ar')"
                :hint="hint('legal_name_ar') ?? t('organization.fields.legal_name_hint')"
                dir="rtl"
                lang="ar"
                :maxlength="200"
                :readonly="!editable"
                :error="errors.legal_name_ar"
              />
              <UiInput
                v-model="form.legal_name_en"
                :label="t('organization.fields.legal_name_en')"
                :hint="hint('legal_name_en')"
                dir="ltr"
                lang="en"
                :maxlength="200"
                :readonly="!editable"
                :error="errors.legal_name_en"
              />
            </div>
            <UiInput
              :model-value="organization.cr_number"
              :label="t('organization.fields.cr_number')"
              :hint="t('organization.fields.cr_number_locked')"
              dir="ltr"
              readonly
            >
              <template #trailing>
                <Lock
                  :size="16"
                  class="text-fg-muted"
                  aria-hidden="true"
                />
              </template>
            </UiInput>
          </div>
        </UiCard>

        <UiCard :title="t('organization.sections.tax')">
          <div class="flex flex-col gap-5">
            <UiSwitch
              v-model="form.vat_registered"
              :label="t('organization.fields.vat_registered')"
              :description="t('organization.fields.vat_registered_hint')"
              :disabled="!editable"
            />
            <UiInput
              v-if="form.vat_registered"
              v-model="form.vat_number"
              :label="t('organization.fields.vat_number')"
              :hint="hint('vat_number') ?? t('organization.fields.vat_number_hint')"
              inputmode="numeric"
              dir="ltr"
              :maxlength="15"
              required
              :readonly="!editable"
              :error="errors.vat_number"
            />
          </div>
        </UiCard>

        <UiCard :title="t('organization.sections.location')">
          <div class="flex flex-col gap-5">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
              <OrganizationRegionSelect
                v-model="form.region_id"
                :label="t('organization.fields.region')"
                required
                :disabled="!editable"
                :error="errors.region_id"
              />
              <UiInput
                v-model="form.city"
                :label="t('organization.fields.city')"
                :hint="hint('city')"
                :maxlength="100"
                required
                :readonly="!editable"
                :error="errors.city"
              />
            </div>
            <div class="flex flex-col gap-3">
              <h4 class="text-sm font-bold text-fg">
                {{ t('organization.sections.national_address') }}
              </h4>
              <OrganizationNationalAddressFields
                v-model="form.national_address"
                :disabled="!editable"
                :highlight="missingAddressParts"
                :errors="{
                  building_number: errors['national_address.building_number'],
                  street: errors['national_address.street'],
                  district: errors['national_address.district'],
                  postal_code: errors['national_address.postal_code'],
                  additional_number: errors['national_address.additional_number'],
                  short_address: errors['national_address.short_address'],
                }"
              />
            </div>
          </div>
        </UiCard>

        <UiCard :title="t('organization.sections.contact')">
          <div class="flex flex-col gap-5">
            <UiInput
              v-model="form.website"
              :label="t('organization.fields.website')"
              :hint="t('organization.fields.website_hint')"
              type="url"
              inputmode="url"
              dir="ltr"
              :readonly="!editable"
              :error="errors.website"
            />
            <UiKeyValueList
              :items="[
                { key: 'email', label: t('organization.fields.email'), value: organization.email, ltr: true },
                { key: 'phone', label: t('organization.fields.phone'), value: formatSaudiMobile(organization.phone), ltr: true },
              ]"
            />
            <p class="text-sm text-fg-muted">
              {{ t('organization.contact_locked') }}
            </p>
          </div>
        </UiCard>

        <UiCard :title="t('organization.sections.activity')">
          <div class="flex flex-col gap-5">
            <OrganizationCategoryPicker
              v-model="form.category_ids"
              :label="t('organization.fields.categories')"
              :hint="t('organization.fields.categories_hint')"
              :disabled="!editable"
              :error="errors.category_ids"
            />
            <UiSwitch
              v-model="form.visible_in_suggestions"
              :label="t('organization.fields.visible_in_suggestions')"
              :description="t('organization.fields.visible_in_suggestions_hint')"
              :disabled="!editable"
            />
          </div>
        </UiCard>

        <div
          v-if="editable"
          class="sticky bottom-0 z-10 -mx-4 flex flex-wrap items-center justify-end gap-3 border-t border-line bg-canvas/95 px-4 py-3 backdrop-blur sm:mx-0 sm:rounded-lg sm:border sm:bg-surface/95"
        >
          <p
            v-if="dirty"
            class="me-auto text-sm text-fg-muted"
            role="status"
          >
            {{ t('common.unsaved_changes') }}
          </p>
          <UiButton
            variant="secondary"
            :disabled="!dirty || saving"
            @click="organization && fill(organization)"
          >
            {{ t('common.actions.discard') }}
          </UiButton>
          <UiButton
            type="submit"
            :loading="saving"
            :disabled="!dirty"
          >
            {{ t('common.actions.save_changes') }}
          </UiButton>
        </div>
      </form>

      <UiCard :title="t('organization.sections.profile_document')">
        <OrganizationProfileDocument
          :organization="organization"
          :editable="editable"
          @updated="onFileUpdated"
        />
      </UiCard>

      <UiCard
        :title="t('organization.sections.features')"
        :description="t('organization.features.contact_bafo')"
      >
        <ul class="flex flex-col gap-3">
          <li
            v-for="feature in FEATURES"
            :key="feature"
            class="flex items-center justify-between gap-4 text-sm"
          >
            <span class="text-fg">{{ t(`organization.features.${feature}`) }}</span>
            <UiBadge
              :tone="organization.features[feature] ? 'primary' : 'neutral'"
              size="sm"
              dot
            >
              {{ organization.features[feature] ? t('organization.features.on') : t('organization.features.off') }}
            </UiBadge>
          </li>
        </ul>
      </UiCard>
    </template>
  </div>
</template>
