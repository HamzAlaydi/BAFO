<script setup lang="ts">
import { startTrial } from '~/services/billing'
import type { HomeAlert, HomeAlertCode } from '~/types/api/competitions'

/**
 * Global banners under the top bar, from `GET /home` `alerts` (SCREENS §2.3). Loaded once per
 * session by the dashboard layout; dismissible for the browser session. Never red.
 */
const { t } = useI18n()
const auth = useAuthStore()
const home = useHomeStore()
const toast = useToast()
const { message } = useErrorMessage()
const startingTrial = ref(false)

const WARNING_CODES: HomeAlertCode[] = ['subscription_expiring', 'subscription_expired']

function text(alert: HomeAlert): string {
  if (alert.code === 'subscription_expiring') {
    const days = Number(alert.params.days_left ?? 0)
    return t('home.alerts.subscription_expiring', { days_left: days }, days)
  }
  return t(`home.alerts.${alert.code}`)
}

async function beginTrial(): Promise<void> {
  startingTrial.value = true
  try {
    await startTrial()
    toast.success(t('home.alerts.trial_started'))
    await Promise.all([auth.fetchMe(), home.load()])
  }
  catch (error) {
    toast.error(message(error))
  }
  finally {
    startingTrial.value = false
  }
}
</script>

<template>
  <div v-if="home.alerts.length > 0">
    <UiBanner
      v-for="alert in home.alerts"
      :key="alert.code"
      :tone="WARNING_CODES.includes(alert.code) ? 'warning' : 'info'"
      dismissible
      @dismiss="home.dismiss(alert.code)"
    >
      {{ text(alert) }}
      <template #action>
        <UiButton
          v-if="alert.code === 'trial_available' && auth.can('billing.purchase')"
          size="sm"
          variant="secondary"
          :loading="startingTrial"
          @click="beginTrial"
        >
          {{ t('home.alerts.actions.start_trial') }}
        </UiButton>
        <UiButton
          v-else-if="alert.code === 'billing_profile_incomplete'"
          size="sm"
          variant="secondary"
          :to="{ path: '/dashboard/organization' }"
        >
          {{ t('home.alerts.actions.complete_profile') }}
        </UiButton>
        <UiButton
          v-else-if="alert.code === 'subscription_expiring' && auth.can('billing.purchase')"
          size="sm"
          variant="secondary"
          to="/dashboard/billing/plans"
        >
          {{ t('home.alerts.actions.renew') }}
        </UiButton>
        <UiButton
          v-else-if="alert.code === 'subscription_expired' || alert.code === 'plan_required'"
          size="sm"
          variant="secondary"
          to="/dashboard/billing/plans"
        >
          {{ t('home.alerts.actions.view_plans') }}
        </UiButton>
      </template>
    </UiBanner>
  </div>
</template>
