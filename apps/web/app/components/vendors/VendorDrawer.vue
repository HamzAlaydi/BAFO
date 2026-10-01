<script setup lang="ts">
import { createVendor, updateVendor } from '~/services/integrations'
import type { Vendor, VendorInput } from '~/types/api/integrations'
import type { ChoiceOption } from '~/types/ui'
import type { ExternalRefDraft } from '~/utils/integrations-display'

/**
 * `VendorDrawer` (SCREENS W25): create or edit a vendor with the API.md §1.9 fields and the external
 * references. Client checks mirror the API for feedback only. `vendor_email_taken` and
 * `external_ref_conflict` (409, `details.existing_id`) offer "Open existing".
 */
const props = defineProps<{ vendor: Vendor | null }>()
const emit = defineEmits<{ saved: [vendor: Vendor, created: boolean], openExisting: [id: string] }>()
const open = defineModel<boolean>('open', { default: false })

const { t } = useI18n()
const { message, bind } = useErrorMessage()

const FIELDS = ['name', 'name_en', 'email', 'contact_name', 'phone', 'cr_number', 'vat_number', 'region_id', 'city', 'category_ids', 'status', 'notes'] as const

const name = ref('')
const nameEn = ref('')
const email = ref('')
const contactName = ref('')
const phone = ref<string | null>(null)
const crNumber = ref('')
const vatNumber = ref('')
const regionId = ref<string | null>(null)
const city = ref('')
const categoryIds = ref<string[]>([])
const status = ref<'active' | 'blocked'>('active')
const notes = ref('')
const refs = ref<ExternalRefDraft[]>([])

const submitted = ref(false)
const saving = ref(false)
const serverErrors = ref<Record<string, string>>({})
const formError = ref<string | null>(null)
const existingId = ref<string | null>(null)

function reset(vendor: Vendor | null): void {
  name.value = vendor?.name ?? ''
  nameEn.value = vendor?.name_en ?? ''
  email.value = vendor?.email ?? ''
  contactName.value = vendor?.contact_name ?? ''
  phone.value = vendor?.phone ?? null
  crNumber.value = vendor?.cr_number ?? ''
  vatNumber.value = vendor?.vat_number ?? ''
  regionId.value = vendor?.region?.id ?? null
  city.value = vendor?.city ?? ''
  categoryIds.value = vendor?.categories.map(category => category.id) ?? []
  status.value = vendor?.status === 'blocked' ? 'blocked' : 'active'
  notes.value = vendor?.notes ?? ''
  refs.value = externalRefDrafts(vendor?.external_refs ?? [])
  submitted.value = false
  serverErrors.value = {}
  formError.value = null
  existingId.value = null
}

watch([open, () => props.vendor], ([isOpen]) => {
  if (isOpen) reset(props.vendor)
}, { immediate: true })

const isEdit = computed(() => props.vendor !== null)

const statusOptions = computed<ChoiceOption<'active' | 'blocked'>[]>(() => [
  { value: 'active', label: t('vendors.statuses.active') },
  { value: 'blocked', label: t('vendors.statuses.blocked') },
])
const statusModel = computed<'active' | 'blocked' | null>({ get: () => status.value, set: value => value && (status.value = value) })

const clientErrors = computed((): Record<string, string | null> => {
  if (!submitted.value) return {}
  const trimmedCr = normalizeDigits(crNumber.value).trim()
  const trimmedVat = normalizeDigits(vatNumber.value).trim()
  return {
    name: !name.value.trim() ? t('validation.required') : name.value.trim().length > 200 ? t('validation.max_length', { max: 200 }) : null,
    email: !email.value.trim() ? t('validation.required') : isEmail(email.value.trim()) ? null : t('validation.email'),
    cr_number: trimmedCr && !isSaudiCrNumber(trimmedCr) ? t('validation.cr_number') : null,
    vat_number: trimmedVat && !isSaudiVatNumber(trimmedVat) ? t('validation.vat_number') : null,
  }
})

const errorFor = (field: string) => serverErrors.value[field] ?? clientErrors.value[field] ?? null

const refsInvalid = computed(() => refs.value.some(ref => !ref.system.trim() || !isExternalSystemSlug(ref.system.trim()) || !ref.type.trim() || !ref.id.trim()))

function body(): VendorInput {
  return {
    name: name.value.trim(),
    name_en: nameEn.value.trim() || null,
    email: email.value.trim(),
    contact_name: contactName.value.trim() || null,
    phone: phone.value,
    cr_number: normalizeDigits(crNumber.value).trim() || null,
    vat_number: normalizeDigits(vatNumber.value).trim() || null,
    region_id: regionId.value,
    city: city.value.trim() || null,
    category_ids: categoryIds.value,
    status: status.value,
    notes: notes.value.trim() || null,
    external_refs: externalRefsPayload(refs.value),
  }
}

async function submit(): Promise<void> {
  submitted.value = true
  serverErrors.value = {}
  formError.value = null
  existingId.value = null
  if (Object.values(clientErrors.value).some(Boolean) || refsInvalid.value || saving.value) return
  saving.value = true
  try {
    const saved = props.vendor ? await updateVendor(props.vendor.id, body()) : await createVendor(body())
    emit('saved', saved, !props.vendor)
    open.value = false
  }
  catch (error) {
    if (error instanceof ApiError && (error.code === 'vendor_email_taken' || error.code === 'external_ref_conflict')) {
      formError.value = t(error.code === 'vendor_email_taken' ? 'vendors.conflict.email' : 'vendors.conflict.ref')
      const id = error.detailString('existing_id')
      existingId.value = id && id !== props.vendor?.id ? id : null
      if (error.code === 'vendor_email_taken') serverErrors.value = { email: t('vendors.conflict.email') }
      return
    }
    const refPaths = error instanceof ApiError ? Object.keys(error.errors).filter(path => path.startsWith('external_refs')) : []
    const bound = bind(error, [...FIELDS, ...refPaths], arrayAliases(error, 'category_ids'))
    serverErrors.value = bound.fields
    formError.value = bound.unmatched[0] ?? (Object.keys(bound.fields).length > 0 ? null : message(error))
  }
  finally {
    saving.value = false
  }
}

function openExisting(): void {
  if (existingId.value) emit('openExisting', existingId.value)
}
</script>

<template>
  <UiDrawer
    v-model:open="open"
    :title="isEdit ? t('vendors.drawer.edit_title') : t('vendors.drawer.create_title')"
    :description="t('vendors.drawer.description')"
    size="lg"
    :dismissible="!saving"
  >
    <form
      id="vendor-form"
      class="flex flex-col gap-6 px-5 py-4"
      novalidate
      @submit.prevent="submit"
    >
      <UiAlert
        v-if="formError"
        tone="danger"
        role="alert"
      >
        {{ formError }}
        <UiButton
          v-if="existingId"
          class="mt-2"
          size="sm"
          variant="secondary"
          @click="openExisting"
        >
          {{ t('vendors.conflict.open_existing') }}
        </UiButton>
      </UiAlert>

      <section class="flex flex-col gap-4">
        <h3 class="text-sm font-bold text-fg">
          {{ t('vendors.drawer.sections.identity') }}
        </h3>
        <UiInput
          v-model="name"
          :label="t('vendors.fields.name')"
          :error="errorFor('name')"
          :maxlength="200"
          required
        />
        <UiInput
          v-model="nameEn"
          :label="t('vendors.fields.name_en')"
          :error="errorFor('name_en')"
          dir="ltr"
          :maxlength="200"
        />
        <div class="grid gap-4 sm:grid-cols-2">
          <UiInput
            v-model="crNumber"
            :label="t('vendors.fields.cr_number')"
            :hint="t('organization.fields.cr_number_hint')"
            :error="errorFor('cr_number')"
            inputmode="numeric"
            dir="ltr"
            :maxlength="10"
          />
          <UiInput
            v-model="vatNumber"
            :label="t('vendors.fields.vat_number')"
            :hint="t('organization.fields.vat_number_hint')"
            :error="errorFor('vat_number')"
            inputmode="numeric"
            dir="ltr"
            :maxlength="15"
          />
        </div>
        <UiSegmented
          v-model="statusModel"
          :options="statusOptions"
          :label="t('vendors.fields.status')"
          :hint="t('vendors.status_hint')"
          :error="errorFor('status')"
        />
      </section>

      <section class="flex flex-col gap-4">
        <h3 class="text-sm font-bold text-fg">
          {{ t('vendors.drawer.sections.contact') }}
        </h3>
        <UiInput
          v-model="email"
          type="email"
          inputmode="email"
          dir="ltr"
          autocomplete="off"
          :label="t('vendors.fields.email')"
          :hint="t('vendors.email_hint')"
          :error="errorFor('email')"
          :maxlength="255"
          required
        />
        <UiInput
          v-model="contactName"
          :label="t('vendors.fields.contact_name')"
          :error="errorFor('contact_name')"
          :maxlength="150"
        />
        <UiPhoneInput
          v-model="phone"
          :label="t('vendors.fields.phone')"
          :error="errorFor('phone')"
        />
      </section>

      <section class="flex flex-col gap-4">
        <h3 class="text-sm font-bold text-fg">
          {{ t('vendors.drawer.sections.classification') }}
        </h3>
        <div class="grid gap-4 sm:grid-cols-2">
          <OrganizationRegionSelect
            v-model="regionId"
            :label="t('vendors.fields.region')"
            :error="errorFor('region_id')"
          />
          <UiInput
            v-model="city"
            :label="t('vendors.fields.city')"
            :error="errorFor('city')"
            :maxlength="100"
          />
        </div>
        <OrganizationCategoryPicker
          v-model="categoryIds"
          :label="t('vendors.fields.categories')"
          :error="errorFor('category_ids')"
        />
      </section>

      <VendorsExternalRefsEditor
        v-model="refs"
        :errors="serverErrors"
        :show-errors="submitted"
      />

      <UiTextarea
        v-model="notes"
        :label="t('vendors.fields.notes')"
        :hint="t('vendors.notes_hint')"
        :error="errorFor('notes')"
        :rows="3"
        :maxlength="2000"
      />
    </form>
    <template #footer>
      <UiButton
        type="submit"
        form="vendor-form"
        :loading="saving"
      >
        {{ isEdit ? t('vendors.drawer.submit_edit') : t('vendors.drawer.submit_create') }}
      </UiButton>
      <UiButton
        variant="secondary"
        :disabled="saving"
        @click="open = false"
      >
        {{ t('common.actions.cancel') }}
      </UiButton>
    </template>
  </UiDrawer>
</template>
