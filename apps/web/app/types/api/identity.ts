/** Identity: users, organizations, memberships, auth, team, account deletion (API.md §1.3, §2.1–§2.3). */
import type { Category, Region } from './catalog'
import type { AppLocale, ApiFile, IsoDateTime, PlanRef, Ulid } from './common'

// ---------- Enums ----------

export type UserStatus = 'active' | 'pending_verification' | 'deleted'
export type OrganizationStatus = 'active' | 'suspended' | 'deleted'
export type OrgRole = 'owner' | 'admin' | 'member'
export type MembershipStatus = 'invited' | 'active' | 'inactive'
export type OtpPurpose = 'email_verification' | 'password_reset'

/** ARCHITECTURE §8.1: the only permission strings the API sends in `Me.permissions`. */
export type Permission
  = | 'organization.update'
    | 'team.manage'
    | 'billing.view'
    | 'billing.purchase'
    | 'competitions.create'
    | 'competitions.manage_all'
    | 'competitions.award'
    | 'participation.submit_offers'
    | 'integrations.manage'
    | 'account.delete_organization'

// ---------- Resources ----------

/** `User` (API.md §2.1). */
export interface User {
  id: Ulid
  name: string
  email: string
  phone: string | null
  locale: AppLocale
  avatar_url: string | null
  status: UserStatus
  email_verified_at: IsoDateTime | null
  created_at: IsoDateTime
}

export interface NationalAddress {
  building_number: string | null
  street: string | null
  district: string | null
  postal_code: string | null
  additional_number: string | null
  short_address: string | null
}

export interface OrganizationFeatures {
  api_enabled: boolean
  auction_enabled: boolean
  sponsorship_enabled: boolean
}

/** `Organization`, own view (`GET /organization`, and `Me.organization`), API.md §2.2. */
export interface Organization {
  id: Ulid
  name: string
  legal_name_ar: string | null
  legal_name_en: string | null
  cr_number: string
  vat_registered: boolean
  vat_number: string | null
  region: Region
  city: string
  national_address: NationalAddress
  website: string | null
  /** Company contact (the owner's e-mail at registration); read-only in the dashboard. */
  email: string
  phone: string
  logo_url: string | null
  profile_document: ApiFile | null
  categories: Category[]
  visible_in_suggestions: boolean
  status: OrganizationStatus
  verified: boolean
  features: OrganizationFeatures
  billing_profile_complete: boolean
  /** Field paths, e.g. `legal_name_ar`, `national_address.building_number`. */
  billing_profile_missing: string[]
  trial_available: boolean
  created_at: IsoDateTime
}

/** `Membership` (team list), API.md §2.3. */
export interface Membership {
  id: Ulid
  role: OrgRole
  can_award: boolean
  can_purchase: boolean
  status: MembershipStatus
  joined_at: IsoDateTime | null
  invited_at: IsoDateTime | null
  user: User
}

/** The caller's own membership inside `Me`. */
export interface MeMembership {
  id: Ulid
  role: OrgRole
  can_award: boolean
  can_purchase: boolean
  status: MembershipStatus
}

export type SubscriptionSource = 'paid' | 'trial' | 'grant'
export type SubscriptionStatus = 'pending_payment' | 'active' | 'superseded' | 'expired' | 'cancelled'

export interface MeSubscription {
  plan: PlanRef
  source: SubscriptionSource
  status: SubscriptionStatus
  ends_at: IsoDateTime
  days_left: number
  total_days: number
}

export interface Entitlements {
  can_issue: boolean
  seats_used: number
  seats_total: number
}

/** `GET /me` (API.md §2.3). */
export interface Me {
  user: User
  organization: Organization
  membership: MeMembership
  permissions: Permission[]
  /** `null` when there is no current subscription. */
  subscription: MeSubscription | null
  entitlements: Entitlements
  unread_notifications_count: number
}

/** `Me` plus the Sanctum plain-text token. */
export interface AuthTokenPayload extends Me {
  token: string
  token_type: 'Bearer'
}

// ---------- Auth requests and responses ----------

export interface NationalAddressInput {
  building_number?: string | null
  street?: string | null
  district?: string | null
  postal_code?: string | null
  additional_number?: string | null
  short_address?: string | null
}

export interface OrganizationInput {
  name: string
  cr_number: string
  region_id: Ulid
  city: string
  vat_registered: boolean
  vat_number?: string | null
  legal_name_ar?: string | null
  legal_name_en?: string | null
  website?: string | null
  national_address?: NationalAddressInput
  category_ids?: Ulid[]
  visible_in_suggestions?: boolean
}

/** `POST /auth/register`. */
export interface RegisterRequest {
  name: string
  email: string
  phone: string
  password: string
  password_confirmation: string
  locale?: AppLocale | null
  organization: OrganizationInput
  accept_terms: boolean
  accept_privacy: boolean
  invitation_token?: string | null
  /** Honeypot (API.md §0.8): a visually hidden field people never fill. */
  website_url: string
}

export interface RegisterResponse {
  email: string
  verification_required: boolean
  otp_expires_at: IsoDateTime | null
}

export interface OtpSendResponse {
  /** `null` for unknown e-mails (the endpoint never reveals which). */
  otp_expires_at: IsoDateTime | null
}

export interface LoginRequest {
  email: string
  password: string
  device_name: string
}

export interface VerifyOtpRequest {
  email: string
  code: string
  purpose: 'email_verification'
  device_name: string
}

export interface ResetPasswordRequest {
  email: string
  code: string
  password: string
  password_confirmation: string
}

export interface TeamInvitationLookup {
  email: string
  name: string
  organization: { name: string, logo_url: string | null }
  role: OrgRole
  expires_at: IsoDateTime
}

export interface AcceptTeamInvitationRequest {
  token: string
  password: string
  password_confirmation: string
  device_name: string
  accept_terms: boolean
}

// ---------- Me and organization ----------

export interface UpdateMeRequest {
  name?: string
  phone?: string
  locale?: AppLocale
}

export interface ChangePasswordRequest {
  current_password: string
  password: string
  password_confirmation: string
}

/** `PATCH /organization`: any register field except `cr_number` (immutable). */
export type UpdateOrganizationRequest = Partial<Omit<OrganizationInput, 'cr_number'>>

// ---------- Team ----------

export interface TeamSeats {
  used: number
  total: number
}

export interface TeamMembersResult {
  members: Membership[]
  seats: TeamSeats
}

export interface CreateTeamMemberRequest {
  name: string
  email: string
  phone?: string | null
  role: Exclude<OrgRole, 'owner'>
  can_award?: boolean | null
  can_purchase?: boolean | null
}

export interface UpdateTeamMemberRequest {
  role?: Exclude<OrgRole, 'owner'>
  can_award?: boolean
  can_purchase?: boolean
  status?: 'active' | 'inactive'
}

// ---------- Account deletion ----------

export type DeletionScope = 'user' | 'organization'

export interface AccountDeletionRequest {
  id: Ulid
  scope: DeletionScope
  status: 'pending'
  scheduled_for: IsoDateTime
  created_at: IsoDateTime
}

/** `details.blockers[]` of `account_deletion_blocked`. */
export interface DeletionBlocker {
  type: 'issued_competition' | 'participation'
  competition_id: Ulid
  title: string
}
