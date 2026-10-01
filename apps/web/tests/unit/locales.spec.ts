import { readdirSync, readFileSync, statSync } from 'node:fs'
import { join } from 'node:path'
import { describe, expect, it } from 'vitest'

type Messages = { [key: string]: string | Messages }

function load(locale: string): Messages {
  return JSON.parse(readFileSync(new URL(`../../i18n/locales/${locale}.json`, import.meta.url), 'utf8')) as Messages
}

function flatten(messages: Messages, prefix = ''): Record<string, string> {
  return Object.entries(messages).reduce<Record<string, string>>((acc, [key, value]) => {
    const path = prefix ? `${prefix}.${key}` : key
    return typeof value === 'string' ? { ...acc, [path]: value } : { ...acc, ...flatten(value, path) }
  }, {})
}

const arTree = load('ar')
const enTree = load('en')
const ar = flatten(arTree)
const en = flatten(enTree)

function placeholders(message: string): string[] {
  return [...message.matchAll(/\{(\w+)\}/g)].map(match => match[1] ?? '').sort()
}

/** CONVENTIONS §6.2 area namespaces. */
const NAMESPACES = new Set([
  'common', 'nav', 'auth', 'home', 'competitions', 'rules', 'live', 'offers', 'invitations', 'qa', 'award', 'bafo',
  'billing', 'sponsorship', 'team', 'profile', 'organization', 'notifications', 'integrations', 'vendors',
  'errors', 'validation', 'glossary', 'landing', 'legal',
])

/** The master error list of CONVENTIONS §8 (every code a client can receive). */
const ERROR_CODES = [
  // §8.2
  'bad_request', 'unauthenticated', 'forbidden', 'not_found', 'method_not_allowed', 'not_acceptable', 'conflict', 'gone',
  'payload_too_large', 'unsupported_media_type', 'session_expired', 'validation_failed', 'app_version_unsupported',
  'too_many_requests', 'server_error', 'service_unavailable', 'maintenance', 'http_error', 'idempotency_key_required',
  'idempotency_key_reused', 'idempotency_request_in_progress', 'invalid_state_transition', 'file_type_not_allowed', 'file_too_large',
  // §8.3
  'invalid_credentials', 'email_not_verified', 'account_inactive', 'organization_suspended', 'otp_invalid', 'otp_expired',
  'otp_too_many_attempts', 'otp_resend_cooldown', 'password_incorrect', 'team_invitation_invalid', 'seat_limit_reached',
  'cannot_modify_owner', 'cannot_modify_self', 'account_deletion_blocked', 'account_deletion_pending', 'invitation_email_mismatch',
  // §8.4
  'competition_not_editable', 'issuer_plan_required', 'auction_not_enabled', 'min_participants_not_met', 'max_participants_exceeded',
  'live_event_capacity_reached', 'extend_invalid', 'invitation_cutoff_passed', 'invitation_invalid',
  'invitation_belongs_to_another_organization', 'join_deadline_passed', 'already_participating', 'terms_not_accepted',
  'not_a_participant', 'comments_closed', 'report_not_available', 'results_not_available',
  // §8.5
  'offer_amount_invalid', 'offer_amount_too_large', 'offer_granularity', 'offer_not_accepting', 'offer_closed', 'offer_not_shortlisted',
  'offer_bafo_already_submitted', 'offer_start_price', 'offer_step_not_met', 'offer_bafo_worse_than_reference',
  'offer_outlier_confirm_required', 'bafo_not_enabled', 'bafo_already_used', 'bafo_shortlist_invalid', 'award_participant_has_no_offer',
  'award_justification_required', 'award_reserve_confirmation_required', 'award_not_active',
  // §8.6
  'plan_required', 'purchase_not_available_on_platform', 'billing_profile_incomplete', 'return_url_not_allowed', 'plan_not_available',
  'seats_out_of_range', 'subscription_downgrade_not_allowed', 'subscription_renewal_too_early', 'trial_not_available', 'coupon_invalid',
  'coupon_expired', 'coupon_not_applicable', 'coupon_exhausted', 'invoice_pdf_not_ready', 'sponsorship_not_enabled', 'sponsorship_locked',
  'sponsorship_payment_required', 'sponsorship_already_funded', 'invalid_webhook', 'gateway_error', 'gateway_not_configured',
  // §8.7
  'invalid_token', 'invalid_client', 'unsupported_grant_type', 'invalid_scope', 'insufficient_scope', 'api_access_disabled',
  'external_ref_conflict', 'vendor_email_taken', 'webhook_url_invalid',
]

describe('locale files', () => {
  it('have the same keys in Arabic and English', () => {
    expect(Object.keys(ar).sort()).toEqual(Object.keys(en).sort())
  })

  it('use the same placeholders in both languages', () => {
    const exempt = new Set(['common.brand.tagline.template']) // the Arabic tagline has no separate "Offer" emphasis
    for (const key of Object.keys(ar).filter(k => !exempt.has(k))) {
      expect(new Set(placeholders(ar[key] ?? '')), key).toEqual(new Set(placeholders(en[key] ?? '')))
    }
  })

  it('have no empty messages', () => {
    for (const [key, value] of [...Object.entries(ar), ...Object.entries(en)]) {
      expect(value.trim(), key).not.toBe('')
    }
  })

  it('use only the CONVENTIONS §6.2 namespaces and lower_snake_case segments', () => {
    expect(Object.keys(arTree).filter(namespace => !NAMESPACES.has(namespace))).toEqual([])
    for (const key of Object.keys(ar)) {
      for (const segment of key.split('.')) expect(segment, key).toMatch(/^[a-z][a-z0-9_]*$/)
    }
  })

  it('never use vue-i18n syntax characters unescaped (@ starts a linked message)', () => {
    for (const [key, value] of [...Object.entries(ar), ...Object.entries(en)]) {
      expect(value.replace(/\{'[^']*'\}/g, ''), key).not.toMatch(/[@$]/)
    }
  })

  it('follow the voice rules: no exclamation marks, no legacy brand names, no "Bid" for a competition', () => {
    for (const [key, value] of [...Object.entries(ar), ...Object.entries(en)]) {
      expect(value, key).not.toMatch(/[!！]/)
      expect(value, key).not.toMatch(/munaqes|monaqus/i)
      // «مناقص» only as part of مناقصة (tender), never as a noun for people or the old brand.
      expect(value.replace(/مناقص[ةات]/g, ''), key).not.toMatch(/مناقص/)
      expect(value, key).not.toMatch(/\bbids?\b/i)
      expect(value, key).not.toMatch(/أقل سعر\b|lowest price/i)
    }
  })

  it('use the glossary terms exactly (BRIEF, CONVENTIONS §6.1)', () => {
    expect(ar['glossary.competition']).toBe('منافسة')
    expect(ar['glossary.tender']).toBe('مناقصة')
    expect(ar['glossary.auction']).toBe('مزايدة')
    expect(ar['glossary.issuer']).toBe('طارح المنافسة')
    expect(ar['glossary.participants']).toBe('المتنافسون')
    expect(ar['glossary.offer']).toBe('عرض')
    expect(ar['glossary.leading_offer']).toBe('العرض المتصدر')
    expect(ar['glossary.award']).toBe('ترسية')
    expect(ar['glossary.fees_covered']).toBe('رسوم مغطّاة')
    expect(ar['glossary.sponsored_pass']).toBe('تصريح مشاركة مغطّاة')
    expect(ar['glossary.bafo_round']).toBe('جولة العرض النهائي')
    expect(ar['competitions.status.closed']).toBe('قيد التقييم')
    expect(ar['competitions.direction.tender']).toBe('مناقصة')
    expect(ar['competitions.direction.auction']).toBe('مزايدة')
    expect(en['glossary.competition']).toBe('Competition')
    expect(en['glossary.leading_offer']).toBe('Leading offer')
    expect(en['glossary.sponsored_pass']).toBe('Sponsored participation pass')
  })

  it('translate every error code of the master list as a plain string (SCREENS §5)', () => {
    for (const code of ERROR_CODES) {
      expect(typeof ar[`errors.${code}`], code).toBe('string')
      expect(typeof en[`errors.${code}`], code).toBe('string')
    }
  })

  it('write Arabic plurals with the 6 forms', () => {
    for (const [key, value] of Object.entries(ar)) {
      if (value.includes(' | ')) expect(value.split(' | '), key).toHaveLength(6)
    }
  })
})

describe('i18n keys used in the app', () => {
  const APP_DIR = new URL('../../app/', import.meta.url).pathname

  function files(dir: string): string[] {
    return readdirSync(dir).flatMap((entry) => {
      const path = join(dir, entry)
      if (statSync(path).isDirectory()) return files(path)
      return /\.(vue|ts)$/.test(entry) ? [path] : []
    })
  }

  it('exist in both locales (static keys)', () => {
    const missing: string[] = []
    const call = /(?<![\w.])(?:t|te)\(\s*(['"`])([a-z][a-z0-9_]*(?:\.[a-z0-9_]+)+)\1/g
    for (const file of files(APP_DIR)) {
      const source = readFileSync(file, 'utf8')
      for (const match of source.matchAll(call)) {
        const key = match[2] ?? ''
        if (!(key in ar) || !(key in en)) missing.push(`${file.replace(APP_DIR, '')}: ${key}`)
      }
    }
    expect(missing).toEqual([])
  })
})
