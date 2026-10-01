<script setup lang="ts">
import { ImageUp, Trash2 } from '@lucide/vue'
import { deleteAvatar, updateMe, uploadAvatar } from '~/services/identity'
import type { AppLocale } from '~/types/api/common'

/**
 * Profile (SCREENS W28): name, phone and preferred language (`PATCH /me`; the language also drives
 * e-mail and push), and the avatar (png, jpg, jpeg or webp, ≤ 2 MB). The e-mail cannot be changed here.
 */
const { t } = useI18n()
const auth = useAuthStore()
const toast = useToast()
const switchLocalePath = useSwitchLocalePath()
const { message, bind } = useErrorMessage()
const avatarInput = useTemplateRef<HTMLInputElement>('avatarInput')

const form = reactive({ name: '', phone: null as string | null, locale: 'ar' as AppLocale })
const errors = ref<Record<string, string | undefined>>({})
const formError = ref<string | null>(null)
const saving = ref(false)
const avatarBusy = ref<'upload' | 'delete' | null>(null)
const avatarError = ref<string | null>(null)
const initial = ref('')

function fill(): void {
  const user = auth.user
  if (!user) return
  form.name = user.name
  form.phone = user.phone
  form.locale = user.locale
  initial.value = JSON.stringify(form)
}

watch(() => auth.user, fill, { immediate: true })

const dirty = computed(() => JSON.stringify(form) !== initial.value)
defineExpose({ dirty })

const localeOptions = computed(() => [
  { value: 'ar' as const, label: t('common.languages.ar') },
  { value: 'en' as const, label: t('common.languages.en') },
])

async function save(): Promise<void> {
  formError.value = null
  errors.value = {
    name: !form.name.trim() ? t('validation.required') : form.name.trim().length > 150 ? t('validation.max_length', { max: 150 }) : undefined,
  }
  if (errors.value.name) return
  saving.value = true
  const localeChanged = form.locale !== auth.user?.locale
  try {
    auth.applyMe(await updateMe({ name: form.name.trim(), phone: form.phone ?? undefined, locale: form.locale }))
    toast.success(t('profile.saved'))
    // The preferred language is also the interface language.
    if (localeChanged) await navigateTo(switchLocalePath(form.locale))
  }
  catch (error) {
    const bound = bind(error, ['name', 'phone', 'locale'])
    errors.value = bound.fields
    formError.value = bound.unmatched[0] ?? (error instanceof ApiError && error.isValidation ? null : message(error))
  }
  finally {
    saving.value = false
  }
}

const AVATAR_ACCEPT = '.png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp'

async function onAvatar(event: Event): Promise<void> {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]
  target.value = ''
  if (!file) return
  avatarError.value = null
  if (!fileMatchesAccept(file, AVATAR_ACCEPT)) {
    avatarError.value = t('errors.file_type_not_allowed')
    return
  }
  if (file.size > 2 * 1024 * 1024) {
    avatarError.value = t('profile.avatar.too_large', { size: 2 })
    return
  }
  avatarBusy.value = 'upload'
  try {
    auth.applyMe(await uploadAvatar(file))
    toast.success(t('profile.avatar.uploaded'))
  }
  catch (error) {
    avatarError.value = message(error)
  }
  finally {
    avatarBusy.value = null
  }
}

async function removeAvatar(): Promise<void> {
  avatarBusy.value = 'delete'
  avatarError.value = null
  try {
    auth.applyMe(await deleteAvatar())
    toast.success(t('profile.avatar.removed'))
  }
  catch (error) {
    avatarError.value = message(error)
  }
  finally {
    avatarBusy.value = null
  }
}
</script>

<template>
  <UiCard :title="t('profile.sections.profile')">
    <div
      v-if="auth.user"
      class="flex flex-col gap-6"
    >
      <div class="flex flex-col gap-2">
        <div class="flex flex-wrap items-center gap-4">
          <UiAvatar
            :name="auth.user.name"
            :src="auth.user.avatar_url"
            size="lg"
          />
          <div class="flex flex-wrap gap-2">
            <input
              ref="avatarInput"
              type="file"
              class="sr-only"
              :accept="AVATAR_ACCEPT"
              tabindex="-1"
              :aria-label="t('profile.avatar.upload')"
              @change="onAvatar"
            >
            <UiButton
              variant="secondary"
              size="sm"
              :icon="ImageUp"
              :loading="avatarBusy === 'upload'"
              :disabled="avatarBusy !== null"
              @click="avatarInput?.click()"
            >
              {{ auth.user.avatar_url ? t('profile.avatar.replace') : t('profile.avatar.upload') }}
            </UiButton>
            <UiButton
              v-if="auth.user.avatar_url"
              variant="danger-ghost"
              size="sm"
              :icon="Trash2"
              :loading="avatarBusy === 'delete'"
              :disabled="avatarBusy !== null"
              @click="removeAvatar"
            >
              {{ t('profile.avatar.remove') }}
            </UiButton>
          </div>
        </div>
        <p class="text-sm text-fg-muted">
          {{ t('profile.avatar.hint', { size: 2 }) }}
        </p>
        <p
          v-if="avatarError"
          class="text-sm font-medium text-danger"
          role="alert"
        >
          {{ avatarError }}
        </p>
      </div>

      <UiAlert
        v-if="formError"
        tone="danger"
        dismissible
        @dismiss="formError = null"
      >
        {{ formError }}
      </UiAlert>

      <form
        class="flex flex-col gap-5"
        novalidate
        @submit.prevent="save"
      >
        <UiInput
          v-model="form.name"
          :label="t('profile.fields.name')"
          autocomplete="name"
          :maxlength="150"
          required
          :error="errors.name"
        />
        <UiInput
          :model-value="auth.user.email"
          :label="t('profile.fields.email')"
          :hint="t('profile.fields.email_locked')"
          dir="ltr"
          readonly
        />
        <UiPhoneInput
          v-model="form.phone"
          :label="t('profile.fields.phone')"
          :error="errors.phone"
        />
        <UiSegmented
          v-model="form.locale"
          :options="localeOptions"
          :label="t('profile.fields.locale')"
          :hint="t('profile.fields.locale_hint')"
          :error="errors.locale"
        />
        <div class="flex justify-end">
          <UiButton
            type="submit"
            :loading="saving"
            :disabled="!dirty"
          >
            {{ t('common.actions.save_changes') }}
          </UiButton>
        </div>
      </form>
    </div>
  </UiCard>
</template>
