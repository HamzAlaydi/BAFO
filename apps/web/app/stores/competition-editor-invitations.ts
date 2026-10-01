/**
 * Staged invitations of the invite picker (SCREENS W15 step 6, W17 "Invite more"), as pure functions:
 * e-mail pasting, de-duplication, the request body, and the all-or-nothing per-row errors
 * (`errors."invitations.{i}.*"` + `details.item_codes`, CONVENTIONS §8.1).
 */
import type { Invitation, InvitationInput, InvitationItemCode } from '~/types/api/competitions'
import { isEmail } from '~/utils/validation'

export type StagedKind = 'email' | 'organization' | 'vendor'

export interface StagedInvitation {
  /** Stable key for the list (`email:…`, `organization:…`, `vendor:…`). */
  key: string
  kind: StagedKind
  email: string | null
  organizationId: string | null
  vendorId: string | null
  /** Display label: the e-mail, organisation or vendor name. */
  label: string
  /** Optional recipient name (`invitations.*.name`, ≤ 150). */
  name: string
  /** Counts only in `selected` sponsorship mode. */
  sponsored: boolean
  /** Server problem for this row after a rejected submit. */
  error: string | null
  errorCode: InvitationItemCode | null
}

export const MAX_INVITATIONS_PER_REQUEST = 100

export function stagedKey(kind: StagedKind, id: string): string {
  return `${kind}:${kind === 'email' ? id.toLowerCase() : id}`
}

export function stageEmail(email: string, name = ''): StagedInvitation {
  const normalized = email.trim().toLowerCase()
  return { key: stagedKey('email', normalized), kind: 'email', email: normalized, organizationId: null, vendorId: null, label: normalized, name, sponsored: false, error: null, errorCode: null }
}

export function stageOrganization(id: string, name: string): StagedInvitation {
  return { key: stagedKey('organization', id), kind: 'organization', email: null, organizationId: id, vendorId: null, label: name, name: '', sponsored: false, error: null, errorCode: null }
}

export function stageVendor(id: string, name: string, email: string | null): StagedInvitation {
  return { key: stagedKey('vendor', id), kind: 'vendor', email, organizationId: null, vendorId: id, label: name, name: '', sponsored: false, error: null, errorCode: null }
}

export function isValidStagedEmail(row: StagedInvitation): boolean {
  return row.kind !== 'email' || (row.email !== null && isEmail(row.email))
}

/**
 * Adds rows, skipping ones already staged or already invited (same e-mail, organisation or vendor).
 * Returns the new list and how many were skipped as duplicates.
 */
export function addStaged(
  current: readonly StagedInvitation[],
  incoming: readonly StagedInvitation[],
  existing: readonly Pick<Invitation, 'email' | 'organization' | 'vendor' | 'status'>[] = [],
): { rows: StagedInvitation[], duplicates: number } {
  const active = existing.filter(invitation => invitation.status !== 'revoked')
  const invitedEmails = new Set(active.map(invitation => invitation.email.toLowerCase()))
  const invitedOrganizations = new Set(active.map(invitation => invitation.organization?.id).filter(Boolean))
  const invitedVendors = new Set(active.map(invitation => invitation.vendor?.id).filter(Boolean))
  const keys = new Set(current.map(row => row.key))
  const rows = [...current]
  let duplicates = 0
  for (const row of incoming) {
    const already = keys.has(row.key)
      || (row.email !== null && invitedEmails.has(row.email.toLowerCase()))
      || (row.organizationId !== null && invitedOrganizations.has(row.organizationId))
      || (row.vendorId !== null && invitedVendors.has(row.vendorId))
    if (already) {
      duplicates += 1
      continue
    }
    keys.add(row.key)
    rows.push(row)
  }
  return { rows, duplicates }
}

/** The `invitations` array of `POST …/invitations` (and of the invite checkout, without `sponsored`). */
export function stagedToInput(rows: readonly StagedInvitation[], withSponsored: boolean): InvitationInput[] {
  return rows.map((row) => {
    const input: InvitationInput = {}
    if (row.organizationId) input.organization_id = row.organizationId
    else if (row.vendorId) input.vendor_id = row.vendorId
    else if (row.email) input.email = row.email
    const name = row.name.trim()
    if (name) input.name = name
    if (withSponsored) input.sponsored = row.sponsored
    return input
  })
}

const ITEM_CODES: readonly InvitationItemCode[] = ['invitation_duplicate', 'cannot_invite_own_organization', 'vendor_blocked', 'vendor_not_found']

function isItemCode(value: unknown): value is InvitationItemCode {
  return typeof value === 'string' && (ITEM_CODES as readonly string[]).includes(value)
}

export interface StagedRowError {
  message: string | null
  code: InvitationItemCode | null
}

/**
 * Maps a bulk 422 onto the staged rows by index (`invitations.2.email` → the third row). Rows without
 * a problem get `null`; paths that match no row are returned as `unmatched` messages.
 */
export function stagedRowErrors(
  error: { errors: Record<string, string[]>, details: Record<string, unknown> },
  rowCount: number,
): { rows: Array<StagedRowError | null>, unmatched: string[] } {
  const rows: Array<StagedRowError | null> = Array.from({ length: rowCount }, () => null)
  const unmatched: string[] = []
  const itemCodes = (error.details.item_codes ?? {}) as Record<string, unknown>
  const indexOf = (path: string): number | null => {
    const match = /^invitations\.(\d+)(?:\.|$)/.exec(path)
    const index = match ? Number(match[1]) : Number.NaN
    return Number.isInteger(index) && index >= 0 && index < rowCount ? index : null
  }
  for (const [path, messages] of Object.entries(error.errors)) {
    const index = indexOf(path)
    if (index === null) {
      if (messages[0]) unmatched.push(messages[0])
      continue
    }
    const code = itemCodes[path]
    rows[index] ??= { message: messages[0] ?? null, code: isItemCode(code) ? code : null }
  }
  for (const [path, code] of Object.entries(itemCodes)) {
    const index = indexOf(path)
    if (index === null || !isItemCode(code)) continue
    const row = rows[index]
    if (!row) rows[index] = { message: null, code }
    else row.code ??= code
  }
  return { rows, unmatched }
}

/** Upserts an invitation (realtime `invitation.updated`, row actions) and keeps the list order. */
export function upsertInvitation(list: readonly Invitation[], invitation: Invitation): Invitation[] {
  const index = list.findIndex(item => item.id === invitation.id)
  if (index === -1) return [...list, invitation]
  const next = [...list]
  next[index] = invitation
  return next
}
