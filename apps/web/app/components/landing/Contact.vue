<script setup lang="ts">
import { CircleCheck, Send } from '@lucide/vue'
import { submitContact } from '~/services/platform'

/**
 * Contact (W01): `POST /contact` with the honeypot (API.md §0.8). Client validation marks the
 * required fields, server field errors are bound by path, `too_many_requests` is explained, and
 * success is confirmed inline. The support contacts (`AppConfig.support`) live in the footer.
 */
const { t } = useI18n()
const { message, bind } = useErrorMessage()

const contact = reactive({ name: '', email: '', phone: null as string | null, company: '', subject: '', message: '', website_url: '' })
const submitted = ref(false)
const busy = ref(false)
const sent = ref(false)
const error = ref<string | null>(null)
const serverErrors = ref<Record<string, string>>({})

const clientErrors = computed((): Record<string, string | null> => {
  if (!submitted.value) return {}
  return {
    name: contact.name.trim() ? null : t('validation.required'),
    email: !contact.email.trim() ? t('validation.required') : isEmail(contact.email.trim()) ? null : t('validation.email'),
    subject: contact.subject.trim() ? null : t('validation.required'),
    message: contact.message.trim() ? null : t('validation.required'),
  }
})

const fieldError = (field: string) => serverErrors.value[field] ?? clientErrors.value[field] ?? null

async function send(): Promise<void> {
  submitted.value = true
  error.value = null
  serverErrors.value = {}
  if (Object.values(clientErrors.value).some(Boolean) || busy.value) return
  busy.value = true
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
    sent.value = true
  }
  catch (cause) {
    const bound = bind(cause, ['name', 'email', 'phone', 'company', 'subject', 'message'])
    serverErrors.value = bound.fields
    error.value = bound.unmatched[0] ?? (Object.keys(bound.fields).length > 0 ? null : message(cause))
  }
  finally {
    busy.value = false
  }
}
</script>

<template>
  <section
    id="contact"
    class="scroll-mt-20 border-t border-line bg-surface-muted"
    aria-labelledby="contact-title"
  >
    <div class="mx-auto grid max-w-6xl gap-10 px-4 py-16 sm:px-6 sm:py-24 lg:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)]">
      <LandingSectionHeading
        id="contact-title"
        :title="t('landing.contact.title')"
        :subtitle="t('landing.contact.body')"
      />
      <UiCard>
        <div
          v-if="sent"
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
          @submit.prevent="send"
        >
          <UiAlert
            v-if="error"
            tone="danger"
            role="alert"
          >
            {{ error }}
          </UiAlert>
          <div class="grid gap-4 sm:grid-cols-2">
            <UiInput
              v-model="contact.name"
              :label="t('landing.contact.fields.name')"
              :error="fieldError('name')"
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
              :error="fieldError('email')"
              autocomplete="email"
              :maxlength="255"
              required
            />
            <UiPhoneInput
              v-model="contact.phone"
              :label="t('landing.contact.fields.phone')"
              :error="fieldError('phone')"
            />
            <UiInput
              v-model="contact.company"
              :label="t('landing.contact.fields.company')"
              :error="fieldError('company')"
              autocomplete="organization"
              :maxlength="150"
            />
          </div>
          <UiInput
            v-model="contact.subject"
            :label="t('landing.contact.fields.subject')"
            :error="fieldError('subject')"
            :maxlength="150"
            required
          />
          <UiTextarea
            v-model="contact.message"
            :label="t('landing.contact.fields.message')"
            :error="fieldError('message')"
            :rows="5"
            :maxlength="5000"
            required
          />
          <AppHoneypot v-model="contact.website_url" />
          <div class="flex justify-end">
            <UiButton
              type="submit"
              :loading="busy"
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
</template>
