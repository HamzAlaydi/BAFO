/**
 * Identity endpoints (API.md §1.3): authentication, the current user and organization, team
 * members and account deletion. Session state lives in `useAuthStore()`; call these from there
 * or from pages.
 */
import type { ApiResponse } from '~/types/api/common'
import type {
  AcceptTeamInvitationRequest,
  AccountDeletionRequest,
  AuthTokenPayload,
  ChangePasswordRequest,
  CreateTeamMemberRequest,
  LoginRequest,
  Me,
  Membership,
  MembershipStatus,
  Organization,
  OtpPurpose,
  OtpSendResponse,
  RegisterRequest,
  RegisterResponse,
  ResetPasswordRequest,
  TeamInvitationLookup,
  TeamMembersResult,
  TeamSeats,
  UpdateMeRequest,
  UpdateOrganizationRequest,
  UpdateTeamMemberRequest,
  VerifyOtpRequest,
} from '~/types/api/identity'
import { getData, getEnvelope, multipart, send, sendData, seg } from './http'

// ---------- Authentication ----------

/** `POST /auth/register` → 201 `{email, verification_required, otp_expires_at}`; no token yet. */
export function register(body: RegisterRequest): Promise<RegisterResponse> {
  return sendData<RegisterResponse>('POST', '/auth/register', body)
}

/** `POST /auth/otp/send` → always 202, the same answer for unknown e-mails (`otp_expires_at` may be null from older servers). */
export function sendOtp(email: string, purpose: OtpPurpose): Promise<OtpSendResponse> {
  return sendData<OtpSendResponse>('POST', '/auth/otp/send', { email, purpose })
}

/** `POST /auth/otp/verify` → `AuthTokenPayload`. */
export function verifyOtp(body: VerifyOtpRequest): Promise<AuthTokenPayload> {
  return sendData<AuthTokenPayload>('POST', '/auth/otp/verify', body)
}

/** `POST /auth/otp/check` (password reset): validates the code without consuming it. */
export function checkOtp(email: string, code: string): Promise<{ valid: boolean }> {
  return sendData<{ valid: boolean }>('POST', '/auth/otp/check', { email, code, purpose: 'password_reset' })
}

export function login(body: LoginRequest): Promise<AuthTokenPayload> {
  return sendData<AuthTokenPayload>('POST', '/auth/login', body)
}

/** Deletes the current token (204). */
export function logout(): Promise<void> {
  return send('POST', '/auth/logout')
}

/** `POST /auth/password/forgot` → always 202. */
export function forgotPassword(email: string): Promise<void> {
  return send('POST', '/auth/password/forgot', { email })
}

/** `POST /auth/password/reset` → 204; every token of the user is revoked. */
export function resetPassword(body: ResetPasswordRequest): Promise<void> {
  return send('POST', '/auth/password/reset', body)
}

export function lookupTeamInvitation(token: string): Promise<TeamInvitationLookup> {
  return sendData<TeamInvitationLookup>('POST', '/auth/team-invitations/lookup', { token })
}

export function acceptTeamInvitation(body: AcceptTeamInvitationRequest): Promise<AuthTokenPayload> {
  return sendData<AuthTokenPayload>('POST', '/auth/team-invitations/accept', body)
}

// ---------- Current user ----------

export function fetchMe(): Promise<Me> {
  return getData<Me>('/me')
}

export function updateMe(body: UpdateMeRequest): Promise<Me> {
  return sendData<Me>('PATCH', '/me', body)
}

/** `PUT /me/password` → 204; revokes every **other** token. */
export function changePassword(body: ChangePasswordRequest): Promise<void> {
  return send('PUT', '/me/password', body)
}

/** png, jpg, jpeg or webp, ≤ 2 MB. */
export function uploadAvatar(file: Blob): Promise<Me> {
  return sendData<Me>('POST', '/me/avatar', multipart({ file }))
}

export function deleteAvatar(): Promise<Me> {
  return sendData<Me>('DELETE', '/me/avatar')
}

// ---------- Organization ----------

export function fetchOrganization(): Promise<Organization> {
  return getData<Organization>('/organization')
}

/** `PATCH /organization` (`organization.update`); `cr_number` is immutable and never sent. */
export function updateOrganization(body: UpdateOrganizationRequest): Promise<Organization> {
  return sendData<Organization>('PATCH', '/organization', body)
}

/** Image ≤ 2 MB. */
export function uploadOrganizationLogo(file: Blob): Promise<Organization> {
  return sendData<Organization>('POST', '/organization/logo', multipart({ file }))
}

export function deleteOrganizationLogo(): Promise<Organization> {
  return sendData<Organization>('DELETE', '/organization/logo')
}

/** PDF ≤ 20 MB. */
export function uploadProfileDocument(file: Blob): Promise<Organization> {
  return sendData<Organization>('POST', '/organization/profile-document', multipart({ file }))
}

export function deleteProfileDocument(): Promise<Organization> {
  return sendData<Organization>('DELETE', '/organization/profile-document')
}

// ---------- Team ----------

/** `GET /team/members` (`team.manage`): not paginated (≤ 50), plus `meta.seats`. */
export async function fetchTeamMembers(status?: MembershipStatus): Promise<TeamMembersResult> {
  const response: ApiResponse<Membership[]> = await getEnvelope<Membership[]>('/team/members', { status })
  const seats = response.meta?.seats as TeamSeats | undefined
  const members = response.data ?? []
  return { members, seats: seats ?? { used: members.filter(m => m.status !== 'inactive').length, total: 0 } }
}

/** 201 `Membership` (status `invited`); `seat_limit_reached` (409, `details.seats`). */
export function createTeamMember(body: CreateTeamMemberRequest): Promise<Membership> {
  return sendData<Membership>('POST', '/team/members', body)
}

export function updateTeamMember(id: string, body: UpdateTeamMemberRequest): Promise<Membership> {
  return sendData<Membership>('PATCH', `/team/members/${seg(id)}`, body)
}

export function removeTeamMember(id: string): Promise<void> {
  return send('DELETE', `/team/members/${seg(id)}`)
}

/** Invited members only; issues a new token, so the old link stops working. */
export function resendTeamInvitation(id: string): Promise<void> {
  return send('POST', `/team/members/${seg(id)}/resend-invitation`)
}

// ---------- Account deletion ----------

/** Owner → scope `organization`; anyone else → scope `user`. */
export function requestAccountDeletion(password: string, reason?: string | null): Promise<AccountDeletionRequest> {
  return sendData<AccountDeletionRequest>('POST', '/account/deletion', { password, reason: reason || null })
}

/** The pending request, or `null`. */
export function fetchAccountDeletion(): Promise<AccountDeletionRequest | null> {
  return getData<AccountDeletionRequest | null>('/account/deletion')
}

export function cancelAccountDeletion(): Promise<void> {
  return send('DELETE', '/account/deletion')
}
