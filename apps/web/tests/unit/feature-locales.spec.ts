import { readFileSync } from 'node:fs'
import { describe, expect, it } from 'vitest'
import { API_SCOPES } from '~/types/api/integrations'
import { API_SCOPE_GROUPS, IMPORT_ROW_CODES, scopeKey } from '~/utils/integrations-display'

/**
 * The billing, integrations, vendors and landing pages build some keys at runtime (statuses, scopes,
 * types). The static-key check in locales.spec.ts cannot see those, so they are listed here.
 */
type Messages = { [key: string]: string | Messages }

function flatten(messages: Messages, prefix = ''): Record<string, string> {
  return Object.entries(messages).reduce<Record<string, string>>((acc, [key, value]) => {
    const path = prefix ? `${prefix}.${key}` : key
    return typeof value === 'string' ? { ...acc, [path]: value } : { ...acc, ...flatten(value, path) }
  }, {})
}

const ar = flatten(JSON.parse(readFileSync(new URL('../../i18n/locales/ar.json', import.meta.url), 'utf8')) as Messages)
const en = flatten(JSON.parse(readFileSync(new URL('../../i18n/locales/en.json', import.meta.url), 'utf8')) as Messages)

const each = (prefix: string, values: readonly string[], suffix = '') => values.map(value => `${prefix}.${value}${suffix}`)

const DYNAMIC_KEYS = [
  ...each('billing.intervals', ['monthly', 'annual']),
  ...each('billing.sources', ['paid', 'trial', 'grant']),
  ...each('billing.statuses', ['pending_payment', 'active', 'superseded', 'expired', 'cancelled']),
  ...each('billing.invoices.types', ['tax_invoice', 'credit_note']),
  ...each('billing.invoices.einvoice', ['cleared', 'reported', 'pending', 'rejected', 'failed']),
  ...each('integrations.scopes.groups', API_SCOPE_GROUPS.map(group => group.key)),
  ...each('integrations.scopes.items', API_SCOPES.map(scopeKey)),
  ...['api_clients', 'webhooks', 'import', 'exports', 'vendors'].flatMap(card => each(`integrations.cards.${card}`, ['title', 'body', 'action'])),
  ...each('integrations.clients.statuses', ['active', 'suspended', 'revoked']),
  ...each('integrations.keys.states', ['active', 'expired', 'revoked']),
  ...each('integrations.deliveries.statuses', ['pending', 'succeeded', 'failed']),
  ...each('integrations.deliveries.filters', ['all', 'pending', 'succeeded', 'failed']),
  ...each('integrations.jobs.statuses', ['queued', 'processing', 'completed', 'failed']),
  ...each('integrations.import.steps', ['template', 'upload', 'review', 'import']),
  ...each('integrations.import.row_codes', IMPORT_ROW_CODES),
  ...['results', 'offer_log', 'awards', 'vendors'].flatMap(type => each(`integrations.exports.types.${type}`, ['label', 'description'])),
  ...each('integrations.exports.formats', ['csv', 'xlsx']),
  ...each('integrations.webhooks.statuses', ['active', 'disabled']),
  ...each('integrations.webhooks.disabled_reasons', ['manual', 'failing']),
  ...each('vendors.statuses', ['active', 'blocked', 'archived']),
  ...each('vendors.sources', ['web', 'api', 'import']),
  ...each('landing.hero.points', ['trial', 'arabic_english', 'web_mobile']),
  ...['issuer', 'participant'].flatMap(kind => ['one', 'two', 'three', 'four'].flatMap(step => each(`landing.how.${kind}.${step}`, ['title', 'body']))),
  ...['tender', 'auction'].flatMap(direction => each(`landing.modes.${direction}`, ['title', 'body', 'examples'])),
  ...each('landing.modes.formats', ['live', 'sealed']),
  ...['choose', 'badge', 'unused'].flatMap(point => each(`landing.sponsored.points.${point}`, ['title', 'body'])),
  ...['api', 'webhooks', 'files'].flatMap(item => each(`landing.erp.items.${item}`, ['title', 'body'])),
  ...['clock', 'privacy', 'sealed', 'ledger', 'award', 'invoices'].flatMap(item => each(`landing.trust.items.${item}`, ['title', 'body'])),
  ...['what', 'tender_auction', 'sealed', 'identities', 'clock', 'sponsored', 'plans', 'erp', 'mobile'].flatMap(item => each(`landing.faq.items.${item}`, ['question', 'answer'])),
  // RELEASE_SCOPE §6 landing: 3-step how-it-works, fairness tiles, audiences, the 12 FAQ items and the demo card.
  ...['create', 'invite', 'award'].flatMap(step => each(`landing.how.steps.issuer.${step}`, ['title', 'body'])),
  ...['invited', 'offer', 'result'].flatMap(step => each(`landing.how.steps.participant.${step}`, ['title', 'body'])),
  ...['tender', 'auction'].flatMap(direction => each(`landing.modes.${direction}`, ['lead'])),
  ...['clock', 'anti_sniping', 'sealed', 'audit'].flatMap(item => each(`landing.fairness.items.${item}`, ['title', 'body'])),
  ...['procurement', 'sellers', 'suppliers'].flatMap(item => each(`landing.audience.items.${item}`, ['term', 'title', 'body'])),
  ...['who_can_join', 'anti_sniping', 'rules', 'award'].flatMap(item => each(`landing.faq.items.${item}`, ['question', 'answer'])),
  ...each('landing.preview.times', ['first', 'second', 'third']),
]

describe('runtime-built i18n keys (billing, integrations, vendors, landing)', () => {
  it('exist in both locales', () => {
    const missing = DYNAMIC_KEYS.filter(key => !(key in ar) || !(key in en))
    expect(missing).toEqual([])
  })

  it('never use «عرض» as a verb for "view" or "show" (it means offer; R2R3 §8.1)', () => {
    for (const [key, value] of Object.entries(ar)) {
      expect(value, key).not.toMatch(/(^|\s)(اعرض|اعرضوا|تعرض|يعرض|يُعرض|تُعرض|نعرض)(\s|$|[،.])/)
      expect(value, key).not.toMatch(/(^|\s)عرض (ال(تفاصيل|كل|مزيد|باقات|دعوات|أقدم|إشعارات)|كل|أسئلة)/)
    }
  })
})
