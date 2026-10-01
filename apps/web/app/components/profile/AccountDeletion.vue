<script setup lang="ts">
import { CalendarClock, TriangleAlert } from '@lucide/vue'
import { cancelAccountDeletion, fetchAccountDeletion, requestAccountDeletion } from '~/services/identity'
import type { AccountDeletionRequest, DeletionBlocker } from '~/types/api/identity'

/**
 * Account deletion (store requirement; SCREENS W28, ARCHITECTURE §13.8). The owner deletes the whole
 * organisation after 14 days; anyone else deletes their personal account only. Invoices, competitions
 * and offer records are kept. A pending request can be cancelled. `account_deletion_blocked` lists
 * the competitions that must end first.
 */
const { t } = useI18n()
const auth = useAuthStore()
const toast = useToast()
const { message } = useErrorMessage()

const pending = ref<AccountDeletionRequest | null>(null)
const loading = ref(true)
const loadError = ref<unknown>(null)
const dialogOpen = ref(false)
const password = ref('')
const reason = ref('')
const passwordError = ref<string | null>(null)
const formError = ref<string | null>(null)
const blockers = ref<DeletionBlocker[]>([])
const submitting = ref(false)
const cancelling = ref(false)

const scope = computed(() => (auth.can('account.delete_organization') ? 'organization' : 'user'))

async function load(): Promise<void> {
  loading.value = true
  loadError.value = null
  try {
    pending.value = await fetchAccountDeletion()
  }
  catch (error) {
    loadError.value = error
  }
  finally {
    loading.value = false
  }
}

onMounted(load)

function openDialog(): void {
  password.value = ''
  reason.value = ''
  passwordError.value = null
  formError.value = null
  blockers.value = []
  dialogOpen.value = true
}

async function submit(): Promise<void> {
  passwordError.value = password.value ? null : t('validation.required')
  if (passwordError.value) return
  submitting.value = true
  formError.value = null
  blockers.value = []
  try {
    pending.value = await requestAccountDeletion(password.value, reason.value.trim() || null)
    dialogOpen.value = false
    toast.success(t('profile.deletion.requested'))
  }
  catch (error) {
    if (!(error instanceof ApiError)) {
      formError.value = t('errors.unknown')
    }
    else if (error.code === 'password_incorrect') {
      passwordError.value = message(error)
    }
    else if (error.code === 'account_deletion_blocked') {
      blockers.value = Array.isArray(error.details.blockers) ? error.details.blockers as DeletionBlocker[] : []
      formError.value = message(error)
    }
    else if (error.code === 'account_deletion_pending') {
      dialogOpen.value = false
      await load()
    }
    else {
      formError.value = error.isValidation ? (error.fieldError('password') ?? error.fieldError('reason') ?? message(error)) : message(error)
    }
  }
  finally {
    submitting.value = false
  }
}

async function cancel(): Promise<void> {
  cancelling.value = true
  try {
    await cancelAccountDeletion()
    pending.value = null
    toast.success(t('profile.deletion.cancelled'))
  }
  catch (error) {
    toast.error(message(error))
  }
  finally {
    cancelling.value = false
  }
}
</script>

<template>
  <UiCard :title="t('profile.sections.deletion')">
    <UiSkeleton
      v-if="loading"
      :lines="3"
    />
    <UiErrorState
      v-else-if="loadError"
      :error="loadError"
      compact
      @retry="load"
    />
    <div
      v-else-if="pending"
      class="flex flex-col gap-4"
    >
      <UiAlert
        tone="warning"
        :icon="CalendarClock"
        :title="t('profile.deletion.pending_title')"
      >
        {{ t('profile.deletion.pending_body') }}
        <UiDateTime
          :value="pending.scheduled_for"
          format="deadline"
          class="font-semibold"
        />
      </UiAlert>
      <div>
        <UiButton
          variant="secondary"
          :loading="cancelling"
          @click="cancel"
        >
          {{ t('profile.deletion.cancel') }}
        </UiButton>
      </div>
    </div>
    <div
      v-else
      class="flex flex-col gap-4 text-sm"
    >
      <p class="text-fg">
        {{ scope === 'organization' ? t('profile.deletion.scope_organization') : t('profile.deletion.scope_user') }}
      </p>
      <p class="text-fg-muted">
        {{ t('profile.deletion.kept') }}
      </p>
      <div>
        <UiButton
          variant="danger"
          :icon="TriangleAlert"
          @click="openDialog"
        >
          {{ scope === 'organization' ? t('profile.deletion.open_organization') : t('profile.deletion.open_user') }}
        </UiButton>
      </div>
    </div>

    <UiConfirmDialog
      v-model:open="dialogOpen"
      :title="scope === 'organization' ? t('profile.deletion.open_organization') : t('profile.deletion.open_user')"
      :description="scope === 'organization' ? t('profile.deletion.scope_organization') : t('profile.deletion.scope_user')"
      :confirm-label="t('profile.deletion.confirm')"
      :busy="submitting"
      :error="formError"
      danger
      @confirm="submit"
    >
      <div
        v-if="blockers.length > 0"
        class="rounded-md border border-line bg-surface-muted p-3 text-sm"
      >
        <p class="mb-2 font-semibold text-fg">
          {{ t('profile.deletion.blockers_title') }}
        </p>
        <ul class="flex flex-col gap-1.5">
          <li
            v-for="blocker in blockers"
            :key="blocker.competition_id"
            class="flex flex-col"
          >
            <NuxtLinkLocale
              :to="`/dashboard/competitions/${blocker.competition_id}`"
              class="link"
            >
              {{ blocker.title }}
            </NuxtLinkLocale>
            <span class="text-xs text-fg-muted">{{ t(`profile.deletion.blocker_types.${blocker.type}`) }}</span>
          </li>
        </ul>
      </div>
      <UiPasswordInput
        v-model="password"
        :label="t('profile.deletion.password')"
        autocomplete="current-password"
        required
        :error="passwordError"
      />
      <UiTextarea
        v-model="reason"
        :label="t('profile.deletion.reason')"
        :hint="t('common.optional')"
        :maxlength="1000"
        :rows="3"
      />
    </UiConfirmDialog>
  </UiCard>
</template>
