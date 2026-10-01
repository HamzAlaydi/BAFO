<script setup lang="ts">
import { LogOut } from '@lucide/vue'

/**
 * W28 Account · `/dashboard/account` (SCREENS §2.4): profile and avatar, password, account deletion
 * and sign out.
 */
definePageMeta({ layout: 'dashboard', middleware: 'auth' })

const { t } = useI18n()
const auth = useAuthStore()
const localePath = useLocalePath()
const signingOut = ref(false)

useSeoMeta({ title: () => t('profile.title') })

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
  <div class="flex flex-col gap-6">
    <UiPageHeader
      :title="t('profile.title')"
      :description="t('profile.subtitle')"
    >
      <template #actions>
        <UiButton
          variant="secondary"
          :icon="LogOut"
          flip-icons
          :loading="signingOut"
          @click="signOut"
        >
          {{ t('nav.sign_out') }}
        </UiButton>
      </template>
    </UiPageHeader>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
      <ProfileForm />
      <ProfilePasswordForm />
    </div>
    <ProfileAccountDeletion />
  </div>
</template>
