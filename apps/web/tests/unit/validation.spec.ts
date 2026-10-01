import { describe, expect, it } from 'vitest'
import {
  formatSaudiMobile,
  isEmail,
  isHttpsUrl,
  isNationalAddressPart,
  isOtpCode,
  isSaudiCrNumber,
  isSaudiMobile,
  isSaudiVatNumber,
  isStrongPassword,
  nationalMobileDigits,
  passwordChecks,
  toSaudiE164,
} from '~/utils/validation'

describe('validation', () => {
  it('checks e-mail shape', () => {
    expect(isEmail('buyer@example.sa')).toBe(true)
    expect(isEmail('buyer@example')).toBe(false)
    expect(isEmail('no at sign')).toBe(false)
  })

  it('checks CR numbers (10 digits, Arabic digits accepted)', () => {
    expect(isSaudiCrNumber('1010123456')).toBe(true)
    expect(isSaudiCrNumber('١٠١٠١٢٣٤٥٦')).toBe(true)
    expect(isSaudiCrNumber('101012345')).toBe(false)
  })

  it('checks VAT numbers (15 digits, 3…3)', () => {
    expect(isSaudiVatNumber('300000000000003')).toBe(true)
    expect(isSaudiVatNumber('310000000000001')).toBe(false)
    expect(isSaudiVatNumber('30000000000003')).toBe(false)
  })

  it('normalises Saudi mobile numbers to E.164 (API.md §0.6)', () => {
    for (const valid of ['0512345678', '512345678', '966512345678', '+966512345678', '00966512345678', '051 234 5678', '٠٥١٢٣٤٥٦٧٨']) {
      expect(toSaudiE164(valid), valid).toBe('+966512345678')
      expect(isSaudiMobile(valid)).toBe(true)
    }
    for (const invalid of ['0412345678', '05123', '+971512345678', '']) {
      expect(toSaudiE164(invalid), invalid).toBeNull()
    }
  })

  it('splits and formats E.164 mobiles for display (SCREENS S6)', () => {
    expect(nationalMobileDigits('+966501234567')).toBe('501234567')
    expect(nationalMobileDigits(null)).toBe('')
    expect(formatSaudiMobile('+966501234567')).toBe('+966 50 123 4567')
    expect(formatSaudiMobile(null)).toBe('')
  })

  it('reports each password rule of ARCHITECTURE §13.9', () => {
    expect(passwordChecks('')).toEqual({ length: false, lowercase: false, uppercase: false, digit: false, symbol: false })
    expect(passwordChecks('Bafo2026#')).toEqual({ length: true, lowercase: true, uppercase: true, digit: true, symbol: true })
    expect(isStrongPassword('Bafo2026#')).toBe(true)
    expect(isStrongPassword('bafo2026#')).toBe(false)
    expect(isStrongPassword('BAFO2026#')).toBe(false)
    expect(isStrongPassword('Bafo#word')).toBe(false)
    expect(isStrongPassword('Bafo2026')).toBe(false)
    expect(isStrongPassword('Bf2#')).toBe(false)
    // Arabic letters have no case, so they do not satisfy the mixed-case rule.
    expect(isStrongPassword('كلمةسر12#')).toBe(false)
  })

  it('accepts only https URLs', () => {
    expect(isHttpsUrl('https://masdar.sa')).toBe(true)
    expect(isHttpsUrl('http://masdar.sa')).toBe(false)
    expect(isHttpsUrl('javascript:alert(1)')).toBe(false)
    expect(isHttpsUrl('masdar.sa')).toBe(false)
  })

  it('checks national address parts', () => {
    expect(isNationalAddressPart('building_number', '1234')).toBe(true)
    expect(isNationalAddressPart('building_number', '123')).toBe(false)
    expect(isNationalAddressPart('postal_code', '١٢٣٤٥')).toBe(true)
    expect(isNationalAddressPart('short_address', 'rrrd2929')).toBe(true)
    expect(isNationalAddressPart('short_address', 'RRR2929')).toBe(false)
  })

  it('checks 6-digit codes', () => {
    expect(isOtpCode('123456')).toBe(true)
    expect(isOtpCode('١٢٣٤٥٦')).toBe(true)
    expect(isOtpCode('12345')).toBe(false)
    expect(isOtpCode('12a456')).toBe(false)
  })
})
