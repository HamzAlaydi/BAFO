<script setup lang="ts">
import { createTeamMember, updateTeamMember } from '~/services/identity'
import type { Membership, TeamSeats } from '~/types/api/identity'

/**
 * `MemberDrawer` (SCREENS W26): invite a member (name, e-mail, phone, role, flags) or edit an existing
 * member's role and flags. `can_award` and `can_purchase` default to on for admins and off for
 * members until the user changes them. No optimistic UI: the table updates from the response.
 * The API lets an editor grant only the flags it holds itself (SECURITY_REVIEW S-02), so a flag
 * the signed-in user lacks is off by default and can only be switched off here.
 */
const props = defineProps<{
  /** The member to edit; null to invite a new one. */
  member: Membership | null
}>()

const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ saved: [member: Membership, created: boolean], seatLimit: [seats: TeamSeats | null] }>()
const { t } = useI18n()
const { message, bind } = useErrorMessage()
const auth = useAuthStore()
const canGrantAward = computed(() => auth.can('competitions.award'))
const canGrantPurchase = computed(() => auth.can('billing.purchase'))

type Role = 'admin' | 'member'
const form = reactive({ name: '', email: '', phone: null as string | null, role: 'member' as Role, can_award: false, can_purchase: false })
const flagsTouched = ref(false)
const errors = ref<Record<string, string | undefined>>({})
const formError = ref<string | null>(null)
const saving = ref(false)

const editing = computed(() => props.member !== null)

watch(open, (isOpen) => {
  if (!isOpen) return
  const member = props.member
  form.name = member?.user.name ?? ''
  form.email = member?.user.email ?? ''
  form.phone = member?.user.phone ?? null
  form.role = member && member.role !== 'owner' ? member.role : 'member'
  form.can_award = member?.can_award ?? false
  form.can_purchase = member?.can_purchase ?? false
  flagsTouched.value = editing.value
  errors.value = {}
  formError.value = null
})

watch(() => form.role, (role) => {
  if (flagsTouched.value) return
  form.can_award = role === 'admin' && canGrantAward.value
  form.can_purchase = role === 'admin' && canGrantPurchase.value
})

const roleOptions = computed(() => [
  { value: 'admin' as const, label: t('team.roles.admin'), description: t('team.roles.admin_description') },
  { value: 'member' as const, label: t('team.roles.member'), description: t('team.roles.member_description') },
])

function validate(): boolean {
  if (editing.value) return true
  errors.value = {
    name: !form.name.trim() ? t('validation.required') : form.name.trim().length > 150 ? t('validation.max_length', { max: 150 }) : undefined,
    email: !form.email.trim() ? t('validation.required') : isEmail(form.email) ? undefined : t('validation.email'),
  }
  return Object.values(errors.value).every(value => !value)
}

async function save(): Promise<void> {
  formError.value = null
  if (!validate()) return
  saving.value = true
  try {
    const saved = props.member
      ? await updateTeamMember(props.member.id, { role: form.role, can_award: form.can_award, can_purchase: form.can_purchase })
      : await createTeamMember({
          name: form.name.trim(),
          email: form.email.trim(),
          phone: form.phone,
          role: form.role,
          can_award: form.can_award,
          can_purchase: form.can_purchase,
        })
    emit('saved', saved, !props.member)
    open.value = false
  }
  catch (error) {
    if (error instanceof ApiError && error.code === 'seat_limit_reached') {
      emit('seatLimit', (error.details.seats as TeamSeats | undefined) ?? null)
      open.value = false
      return
    }
    const bound = bind(error, ['name', 'email', 'phone', 'role', 'can_award', 'can_purchase'])
    errors.value = { ...errors.value, ...bound.fields }
    formError.value = bound.unmatched[0] ?? (error instanceof ApiError && error.isValidation ? null : message(error))
  }
  finally {
    saving.value = false
  }
}
</script>

<template>
  <UiDrawer
    v-model:open="open"
    :title="editing ? t('team.drawer.edit_title') : t('team.drawer.invite_title')"
    :description="editing ? member?.user.name : t('team.drawer.invite_description')"
    :dismissible="!saving"
  >
    <form
      id="team-member-form"
      class="flex flex-col gap-5 p-5"
      novalidate
      @submit.prevent="save"
    >
      <UiAlert
        v-if="formError"
        tone="danger"
      >
        {{ formError }}
      </UiAlert>
      <template v-if="!editing">
        <UiInput
          v-model="form.name"
          :label="t('team.fields.name')"
          autocomplete="off"
          :maxlength="150"
          required
          :error="errors.name"
        />
        <UiInput
          v-model="form.email"
          :label="t('team.fields.email')"
          type="email"
          inputmode="email"
          autocomplete="off"
          dir="ltr"
          required
          :error="errors.email"
        />
        <UiPhoneInput
          v-model="form.phone"
          :label="t('team.fields.phone')"
          :hint="t('common.optional')"
          :error="errors.phone"
        />
      </template>
      <UiKeyValueList
        v-else-if="member"
        :items="[
          { key: 'email', label: t('team.fields.email'), value: member.user.email, ltr: true },
          { key: 'phone', label: t('team.fields.phone'), value: formatSaudiMobile(member.user.phone), ltr: true },
        ]"
      />
      <UiRadioGroup
        v-model="form.role"
        :options="roleOptions"
        :label="t('team.fields.role')"
        required
        :error="errors.role"
      />
      <fieldset class="flex flex-col gap-4 rounded-lg border border-line p-4">
        <legend class="px-1 text-sm font-semibold text-fg">
          {{ t('team.fields.permissions') }}
        </legend>
        <UiSwitch
          :model-value="form.can_award"
          :disabled="!canGrantAward && !member?.can_award"
          :label="t('team.fields.can_award')"
          :description="t('team.fields.can_award_hint')"
          @update:model-value="form.can_award = $event; flagsTouched = true"
        />
        <UiSwitch
          :model-value="form.can_purchase"
          :disabled="!canGrantPurchase && !member?.can_purchase"
          :label="t('team.fields.can_purchase')"
          :description="t('team.fields.can_purchase_hint')"
          @update:model-value="form.can_purchase = $event; flagsTouched = true"
        />
      </fieldset>
    </form>
    <template #footer>
      <UiButton
        variant="secondary"
        :disabled="saving"
        @click="open = false"
      >
        {{ t('common.actions.cancel') }}
      </UiButton>
      <UiButton
        type="submit"
        form="team-member-form"
        :loading="saving"
      >
        {{ editing ? t('common.actions.save') : t('team.drawer.invite_submit') }}
      </UiButton>
    </template>
  </UiDrawer>
</template>
