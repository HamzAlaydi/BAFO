import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import {
  isBillingInterval,
  isHostedCheckoutUrl,
  parsePositiveInt,
  paymentOutcome,
  planIntervalPrice,
  recallCheckout,
  rememberCheckout,
  safeDashboardReturn,
  stripLocalePrefix,
} from '~/utils/billing-display'
import { makeCustomPlan, makePlan } from '../fixtures/billing'

describe('planIntervalPrice', () => {
  it('returns the server price and a list price only when it is higher', () => {
    const plan = makePlan()
    expect(planIntervalPrice(plan, 'monthly')).toEqual({ price: 150000, listPrice: 300000 })
    // Annual list price equals the price: nothing to strike through.
    expect(planIntervalPrice(plan, 'annual')).toEqual({ price: 1500000, listPrice: null })
  })

  it('has no price for the custom plan', () => {
    expect(planIntervalPrice(makeCustomPlan(), 'monthly')).toEqual({ price: null, listPrice: null })
  })
})

describe('query parsing', () => {
  it('accepts only billing intervals', () => {
    expect(isBillingInterval('monthly')).toBe(true)
    expect(isBillingInterval('annual')).toBe(true)
    expect(isBillingInterval('weekly')).toBe(false)
    expect(isBillingInterval(undefined)).toBe(false)
  })

  it('parses positive integers, Arabic-Indic digits included', () => {
    expect(parsePositiveInt('12')).toBe(12)
    expect(parsePositiveInt('١٢')).toBe(12)
    expect(parsePositiveInt(['7', '8'])).toBe(7)
    expect(parsePositiveInt('0')).toBeNull()
    expect(parsePositiveInt('-3')).toBeNull()
    expect(parsePositiveInt('4.5')).toBeNull()
    expect(parsePositiveInt('abc')).toBeNull()
    expect(parsePositiveInt(null)).toBeNull()
  })

  it('keeps only same-origin dashboard return paths', () => {
    expect(safeDashboardReturn('/ar/dashboard/competitions/01j9')).toBe('/ar/dashboard/competitions/01j9')
    expect(safeDashboardReturn('/dashboard/competitions/01j9')).toBe('/dashboard/competitions/01j9')
    expect(safeDashboardReturn('/en/dashboard')).toBe('/en/dashboard')
    expect(safeDashboardReturn('https://evil.example/ar/dashboard')).toBeNull()
    expect(safeDashboardReturn('//evil.example/dashboard')).toBeNull()
    expect(safeDashboardReturn('/\\evil.example')).toBeNull()
    expect(safeDashboardReturn('/ar/auth/login')).toBeNull()
    expect(safeDashboardReturn('/ar/dashboardx')).toBeNull()
  })

  it('strips the locale prefix for localised links', () => {
    expect(stripLocalePrefix('/ar/dashboard/billing')).toBe('/dashboard/billing')
    expect(stripLocalePrefix('/en/dashboard?x=1')).toBe('/dashboard?x=1')
    expect(stripLocalePrefix('/dashboard/team')).toBe('/dashboard/team')
    expect(stripLocalePrefix('/arabic')).toBe('/arabic')
    expect(stripLocalePrefix('/ar')).toBe('/')
  })
})

describe('paymentOutcome (SCREENS W33)', () => {
  it('lets a terminal status win', () => {
    expect(paymentOutcome({ status: 'succeeded' }, 'polling')).toBe('succeeded')
    expect(paymentOutcome({ status: 'failed' }, 'done')).toBe('failed')
    expect(paymentOutcome({ status: 'expired' }, 'done')).toBe('expired')
    expect(paymentOutcome({ status: 'refunded' }, 'done')).toBe('refunded')
  })

  it('is checking while pending, and still pending only after verify', () => {
    expect(paymentOutcome(null, 'idle')).toBe('checking')
    expect(paymentOutcome({ status: 'pending' }, 'polling')).toBe('checking')
    expect(paymentOutcome({ status: 'pending' }, 'verifying')).toBe('checking')
    expect(paymentOutcome({ status: 'pending' }, 'still_pending')).toBe('still_pending')
  })
})

describe('isHostedCheckoutUrl', () => {
  it('follows only http(s) URLs', () => {
    expect(isHostedCheckoutUrl('http://localhost:8000/pay/fake/01j')).toBe(true)
    expect(isHostedCheckoutUrl('https://checkout.example/x')).toBe(true)
    expect(isHostedCheckoutUrl('javascript:alert(1)')).toBe(false)
    expect(isHostedCheckoutUrl('/relative')).toBe(false)
    expect(isHostedCheckoutUrl(null)).toBe(false)
  })
})

describe('checkout memory (sessionStorage)', () => {
  const store = new Map<string, string>()
  const storage = {
    getItem: (key: string) => store.get(key) ?? null,
    setItem: (key: string, value: string) => void store.set(key, value),
    removeItem: (key: string) => void store.delete(key),
  }

  beforeEach(() => {
    store.clear()
    Object.defineProperty(globalThis, 'sessionStorage', { value: storage, configurable: true })
  })

  afterEach(() => {
    Reflect.deleteProperty(globalThis, 'sessionStorage')
  })

  it('remembers how to go back and retry, keyed by payment id', () => {
    rememberCheckout('pay1', { returnTo: '/ar/dashboard/competitions/c1', planId: 'p1', interval: 'annual', seats: 6 })
    expect(recallCheckout('pay1')).toEqual({ returnTo: '/ar/dashboard/competitions/c1', planId: 'p1', interval: 'annual', seats: 6 })
    expect(recallCheckout('other')).toBeNull()
  })

  it('drops an unsafe return path that was tampered with in storage', () => {
    store.set('bafo.checkout_memory', JSON.stringify({ pay1: { returnTo: 'https://evil.example', planId: 'p1', interval: 'monthly', seats: null } }))
    expect(recallCheckout('pay1')?.returnTo).toBeNull()
  })

  it('survives unavailable or corrupt storage', () => {
    store.set('bafo.checkout_memory', '{not json')
    expect(recallCheckout('pay1')).toBeNull()
    Reflect.deleteProperty(globalThis, 'sessionStorage')
    expect(() => rememberCheckout('pay1', { returnTo: null, planId: 'p1', interval: 'monthly', seats: null })).not.toThrow()
    expect(recallCheckout('pay1')).toBeNull()
  })
})
