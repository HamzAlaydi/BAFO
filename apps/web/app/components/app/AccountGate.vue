<script setup lang="ts">
import { Building2, UserX } from '@lucide/vue'

/**
 * Full-page account gate (SCREENS S8): `account_inactive` or `organization_suspended`, with the
 * support contacts from `AppConfig.support` and a way out (Sign out, or back to sign-in).
 */
const props = defineProps<{
  gate: 'account_inactive' | 'organization_suspended'
  /** On the sign-in page there is no session to end. */
  signedOut?: boolean
}>()

const emit = defineEmits<{ back: [] }>()
const { t } = useI18n()
const auth = useAuthStore()
const localePath = useLocalePath()
const signingOut = ref(false)

async function signOut(): Promise<void> {
  signingOut.value = true
  try {
    await auth.logout()
    await navigateTo(localePath('/auth/login'))
  }
  finally {
    signingOut.value = false
  }
}
</script>

<template>
  <div class="mx-auto flex w-full max-w-lg flex-col items-center gap-6 py-10 text-center">
    <span
      class="flex size-16 items-center justify-center rounded-full bg-warning-soft text-warning-soft-fg"
      aria-hidden="true"
    >
      <component
        :is="props.gate === 'organization_suspended' ? Building2 : UserX"
        :size="30"
      />
    </span>
    <div>
      <h1 class="text-2xl font-bold text-fg">
        {{ t(`common.account_gate.${gate}.title`) }}
      </h1>
      <p
        class="mt-2 text-fg-muted"
        role="alert"
      >
        {{ t(`errors.${gate}`) }}
      </p>
    </div>
    <div class="w-full rounded-lg border border-line bg-surface p-5 text-start">
      <p class="mb-3 font-semibold text-fg">
        {{ t('common.support.title') }}
      </p>
      <AppSupportContacts />
    </div>
    <UiButton
      v-if="signedOut"
      variant="secondary"
      @click="emit('back')"
    >
      {{ t('auth.login.back_to_sign_in') }}
    </UiButton>
    <UiButton
      v-else
      variant="secondary"
      :loading="signingOut"
      @click="signOut"
    >
      {{ t('nav.sign_out') }}
    </UiButton>
  </div>
</template>
