import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import { makeAppConfig } from '../fixtures/api'
import { makePlan, makeCustomPlan } from '../fixtures/billing'
import LandingPage from '~/pages/index.vue'

/**
 * W01 landing (RELEASE_SCOPE §6): sections, the release-scope gates (FAQ 10 in `core`, 12 in `full`;
 * sealed, sponsored and ERP content; the API terms link), the SSR plans teaser, the head tags with
 * the JSON-LD graph, and the contact form.
 */
const billing = vi.hoisted(() => ({ fetchPlans: vi.fn() }))
const platform = vi.hoisted(() => ({ submitContact: vi.fn(), fetchAppConfig: vi.fn(), fetchServerTime: vi.fn() }))
vi.mock('~/services/billing', () => billing)
vi.mock('~/services/platform', () => platform)

const t = (key: string, params: Record<string, unknown> = {}, plural?: number) =>
  plural === undefined ? useNuxtApp().$i18n.t(key, params) : useNuxtApp().$i18n.t(key, params, plural)

/** vue-i18n renders a missing key as the key itself: none may reach the page. */
function expectNoRawKeys(text: string): void {
  expect(text).not.toMatch(/\b(landing|billing|common|errors|validation|glossary|competitions)\.[a-z_]+\.[a-z_]+/)
}

async function flush(times = 6): Promise<void> {
  for (let i = 0; i < times; i++) await new Promise(resolve => setTimeout(resolve, 0))
}

type Scope = 'core' | 'full'

function setScope(scope: Scope): void {
  useAppConfigStore().config = makeAppConfig({}, scope)
}

async function mountLanding(scope: Scope) {
  setScope(scope)
  const wrapper = await mountSuspended(LandingPage, { attachTo: document.body })
  await flush()
  return wrapper
}

function headJsonLd(): Array<Record<string, unknown>> {
  const script = document.head.querySelector('script[type="application/ld+json"]')
  expect(script, 'the JSON-LD script is rendered into <head>').not.toBeNull()
  return JSON.parse(script!.textContent ?? '[]') as Array<Record<string, unknown>>
}

function headMeta(selector: string): string | null {
  return document.head.querySelector<HTMLMetaElement>(selector)?.getAttribute('content') ?? null
}

beforeEach(() => {
  for (const group of [billing, platform]) {
    for (const fn of Object.values(group)) fn.mockReset()
  }
  platform.fetchServerTime.mockResolvedValue({ server_time: new Date().toISOString() })
  platform.fetchAppConfig.mockResolvedValue(makeAppConfig({}, 'core'))
  billing.fetchPlans.mockResolvedValue([])
  // The plans are SSR data (`useAsyncData`), cached per key across mounts in one Nuxt app.
  clearNuxtData('landing:plans')
})

describe('W01 landing in release scope core', () => {
  it('renders the nine sections in order with one h1 and no raw keys', async () => {
    billing.fetchPlans.mockResolvedValue([makePlan({ is_featured: false }), makePlan({ id: '01j9plan00000000000000plus', code: 'plus', name: 'باقة بلس' }), makeCustomPlan()])
    const wrapper = await mountLanding('core')

    expect(wrapper.findAll('h1')).toHaveLength(1)
    expect(wrapper.get('h1').text()).toBe(t('landing.hero.title'))
    expect(wrapper.find('main#main').exists()).toBe(true)
    expect(wrapper.find('header').exists()).toBe(true)
    expect(wrapper.find('footer').exists()).toBe(true)

    const sections = wrapper.findAll('main > section, main > * > section').map(section => section.attributes('aria-labelledby'))
    expect(sections).toEqual(['hero-title', 'how-title', 'modes-title', 'fairness-title', 'audience-title', 'plans-title', 'faq-title', 'cta-title', 'contact-title'])

    // Every section is labelled by its own heading: the hero by the h1, the others by an h2.
    for (const id of sections) expect(wrapper.find(`#${id}`).element.tagName, id).toBe(id === 'hero-title' ? 'H1' : 'H2')

    expect(wrapper.text()).toContain(t('landing.how.steps.issuer.create.title'))
    expect(wrapper.text()).toContain(t('landing.modes.tender.lead'))
    expect(wrapper.text()).toContain(t('landing.fairness.items.anti_sniping.title'))
    expect(wrapper.text()).toContain(t('landing.audience.items.procurement.title'))
    expect(wrapper.text()).toContain(t('landing.cta.title'))
    expectNoRawKeys(wrapper.text())
    wrapper.unmount()
  })

  it('hides the sealed, sponsored and ERP content, the API terms link and the theme menu', async () => {
    const wrapper = await mountLanding('core')
    expect(wrapper.text()).not.toContain(t('landing.modes.sealed_line'))
    expect(wrapper.text()).not.toContain(t('landing.fairness.items.sealed.title'))
    expect(wrapper.text()).not.toContain(t('landing.sponsored.title'))
    expect(wrapper.text()).not.toContain(t('landing.erp.title'))
    expect(wrapper.text()).not.toContain(t('legal.codes.api_terms'))
    expect(wrapper.get('header').text()).not.toContain(t('common.theme.label'))
    expect(wrapper.findAll('a').some(link => link.attributes('href')?.endsWith('/docs/api'))).toBe(false)
    wrapper.unmount()
  })

  it('shows the 10 always-on FAQ items and mirrors them in the FAQPage JSON-LD', async () => {
    const wrapper = await mountLanding('core')
    const details = wrapper.findAll('#faq details')
    expect(details).toHaveLength(10)
    expect(details[0]!.find('h3').text()).toBe(t('landing.faq.items.what.question'))
    expect(wrapper.get('#faq').text()).not.toContain(t('landing.faq.items.sponsored.question'))
    expect(wrapper.get('#faq').text()).not.toContain(t('landing.faq.items.erp.question'))

    const graph = headJsonLd()
    expect(graph.map(node => node['@type'])).toEqual(['Organization', 'WebSite', 'SoftwareApplication', 'FAQPage'])
    const faq = graph[3] as { mainEntity: Array<{ name: string }> }
    expect(faq.mainEntity.map(item => item.name)).toEqual(details.map(detail => detail.find('h3').text()))
    wrapper.unmount()
  })

  it('keeps one FAQ item open at a time', async () => {
    const wrapper = await mountLanding('core')
    const details = wrapper.findAll('#faq details').map(detail => detail.element as HTMLDetailsElement)
    details[0]!.open = true
    details[0]!.dispatchEvent(new Event('toggle'))
    details[1]!.open = true
    details[1]!.dispatchEvent(new Event('toggle'))
    await flush()
    expect(details[0]!.open).toBe(false)
    expect(details[1]!.open).toBe(true)
    wrapper.unmount()
  })

  it('writes the per-locale title, description, Open Graph and Twitter tags', async () => {
    const wrapper = await mountLanding('core')
    await flush()
    expect(document.title).toContain(t('landing.meta.title'))
    expect(headMeta('meta[name="description"]')).toBe(t('landing.meta.description'))
    expect(headMeta('meta[property="og:title"]')).toBe(t('landing.meta.og_title'))
    expect(headMeta('meta[property="og:type"]')).toBe('website')
    expect(headMeta('meta[property="og:url"]')).toBe('https://bafo-web-demo.vercel.app/ar')
    expect(headMeta('meta[property="og:image"]')).toBe('https://bafo-web-demo.vercel.app/og/bafo-og-ar.png')
    expect(headMeta('meta[property="og:image:width"]')).toBe('1200')
    expect(headMeta('meta[property="og:image:height"]')).toBe('630')
    expect(headMeta('meta[property="og:locale"]')).toBe('ar_SA')
    expect(headMeta('meta[name="twitter:card"]')).toBe('summary_large_image')
    expect(headMeta('meta[name="twitter:image"]')).toBe('https://bafo-web-demo.vercel.app/og/bafo-og-ar.png')
    expect(headMeta('meta[name="keywords"]')).toBe(t('landing.meta.keywords'))
    wrapper.unmount()
  })

  it('mentions sealed offers in the description only while the sealed format is on (§6.1)', async () => {
    const core = await mountLanding('core')
    await flush()
    expect(t('landing.meta.description')).not.toMatch(/sealed|مغلق/i)
    expect(headMeta('meta[property="og:description"]')).toBe(t('landing.meta.description'))
    expect((headJsonLd()[2] as { description?: string }).description).toBe(t('landing.meta.description'))
    core.unmount()

    const full = await mountLanding('full')
    await flush()
    expect(headMeta('meta[name="description"]')).toBe(t('landing.meta.description_sealed'))
    expect(headMeta('meta[name="twitter:description"]')).toBe(t('landing.meta.description_sealed'))
    full.unmount()
  })

  it('shows up to three fixed plans with the interval toggle and hides the custom plan', async () => {
    billing.fetchPlans.mockResolvedValue([makePlan(), makeCustomPlan()])
    const wrapper = await mountLanding('core')
    const plans = wrapper.get('#plans')
    const cards = plans.findAll('article').map(card => card.find('h3').text())
    expect(cards).toEqual([makePlan().name])
    expect(plans.text()).toContain(t('landing.plans.custom'))
    expect(plans.text()).toContain(t('common.prices_exclude_vat'))
    expect(plans.text()).toContain(t('billing.common.per_month'))
    await plans.get('input[type="radio"][value="annual"]').setValue(true)
    await flush()
    expect(plans.text()).toContain(t('billing.common.per_year'))
    expect(wrapper.findAll('header nav a').some(link => link.attributes('href') === '#plans')).toBe(true)
    wrapper.unmount()
  })

  it('hides the plans section and its nav link when plans cannot be loaded', async () => {
    billing.fetchPlans.mockRejectedValue(new ApiError({ status: 500, code: 'server_error', message: '', errors: {} }))
    const wrapper = await mountLanding('core')
    expect(wrapper.find('#plans').exists()).toBe(false)
    expect(wrapper.findAll('header nav a').some(link => link.attributes('href') === '#plans')).toBe(false)
    expect(wrapper.text()).toContain(t('landing.faq.title'))
    wrapper.unmount()
  })

  it('switches how it works to the three participant steps', async () => {
    const wrapper = await mountLanding('core')
    expect(wrapper.findAll('#how-it-works ol li')).toHaveLength(3)
    await wrapper.findAll('[role="tab"]').find(tab => tab.text().includes(t('landing.how.tabs.participant')))!.trigger('click')
    await flush()
    expect(wrapper.text()).toContain(t('landing.how.steps.participant.result.title'))
    expect(wrapper.findAll('#how-it-works ol li')).toHaveLength(3)
    wrapper.unmount()
  })

  it('renders the live demo as a tender with the leading standing and the server clock note', async () => {
    const wrapper = await mountLanding('core')
    const demo = wrapper.get(`article[aria-label="${t('landing.preview.label')}"]`)
    expect(demo.text()).toContain(t('competitions.direction.tender'))
    expect(demo.text()).toContain(t('landing.preview.leading'))
    expect(demo.text()).toContain(t('landing.preview.server_clock'))
    expect(demo.findAll('ol li')).toHaveLength(3)
    expect(demo.find('[role="timer"]').exists()).toBe(true)
    wrapper.unmount()
  })
})

describe('W01 landing in release scope full', () => {
  it('brings back the sealed lines, the sponsored and ERP sections, 12 FAQ items and the API terms link', async () => {
    const wrapper = await mountLanding('full')
    expect(wrapper.text()).toContain(t('landing.modes.sealed_line'))
    expect(wrapper.text()).toContain(t('landing.fairness.items.sealed.title'))
    expect(wrapper.text()).toContain(t('landing.sponsored.title'))
    expect(wrapper.text()).toContain(t('landing.erp.title'))
    expect(wrapper.findAll('a').some(link => link.attributes('href') === 'http://api.bafo.test/docs/api')).toBe(true)
    expect(wrapper.text()).toContain(t('legal.codes.api_terms'))
    expect(wrapper.get('header').text()).toContain(t('common.theme.label'))

    const details = wrapper.findAll('#faq details')
    expect(details).toHaveLength(12)
    const faq = headJsonLd()[3] as { mainEntity: Array<{ name: string }> }
    expect(faq.mainEntity).toHaveLength(12)
    expect(faq.mainEntity.at(-2)?.name).toBe(t('landing.faq.items.sponsored.question'))
    expect(faq.mainEntity.at(-1)?.name).toBe(t('landing.faq.items.erp.question'))
    expectNoRawKeys(wrapper.text())
    wrapper.unmount()
  })

  it('reacts when the flags change after the first render', async () => {
    const wrapper = await mountLanding('core')
    expect(wrapper.findAll('#faq details')).toHaveLength(10)
    setScope('full')
    await flush()
    expect(wrapper.findAll('#faq details')).toHaveLength(12)
    wrapper.unmount()
  })
})

describe('W01 contact form', () => {
  it('sends the contact form with the empty honeypot and confirms inline', async () => {
    platform.submitContact.mockResolvedValue({ id: '01j9msg0000000000000000001' })
    const wrapper = await mountLanding('core')
    const form = wrapper.get('#contact form')
    await form.trigger('submit')
    await flush()
    expect(platform.submitContact).not.toHaveBeenCalled()
    expect(wrapper.get('#contact').text()).toContain(t('validation.required'))

    await form.get('input[autocomplete="name"]').setValue('سارة')
    await form.get('input[type="email"]').setValue('sara@issuer.sa')
    const texts = form.findAll('input[type="text"]:not([name="website_url"]):not([autocomplete])')
    await texts.at(-1)!.setValue('طلب جلسة تعريفية')
    await form.get('textarea').setValue('نود معرفة المزيد.')
    await form.trigger('submit')
    await flush()
    expect(platform.submitContact).toHaveBeenCalledWith(expect.objectContaining({ name: 'سارة', email: 'sara@issuer.sa', subject: 'طلب جلسة تعريفية', website_url: '' }))
    expect(wrapper.text()).toContain(t('landing.contact.sent_title'))
    wrapper.unmount()
  })

  it('explains the rate limit on the contact form', async () => {
    platform.submitContact.mockRejectedValue(new ApiError({ status: 429, code: 'too_many_requests', message: '', errors: {} }))
    const wrapper = await mountLanding('core')
    const form = wrapper.get('#contact form')
    await form.get('input[autocomplete="name"]').setValue('سارة')
    await form.get('input[type="email"]').setValue('sara@issuer.sa')
    await form.findAll('input[type="text"]:not([name="website_url"]):not([autocomplete])').at(-1)!.setValue('موضوع')
    await form.get('textarea').setValue('رسالة')
    await form.trigger('submit')
    await flush()
    expect(wrapper.text()).toContain(t('errors.too_many_requests'))
    wrapper.unmount()
  })
})
