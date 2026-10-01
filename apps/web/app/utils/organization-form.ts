import type { NationalAddress, NationalAddressInput } from '../types/api/identity'
import { normalizeDigits } from './digits'
import { NATIONAL_ADDRESS_PATTERNS } from './validation'

/** Form state of the national address (every part a string; empty = not given). */
export interface NationalAddressForm {
  building_number: string
  street: string
  district: string
  postal_code: string
  additional_number: string
  short_address: string
}

export const NATIONAL_ADDRESS_PARTS: ReadonlyArray<keyof NationalAddressForm> = [
  'building_number',
  'street',
  'district',
  'postal_code',
  'additional_number',
  'short_address',
]

export function emptyNationalAddress(): NationalAddressForm {
  return { building_number: '', street: '', district: '', postal_code: '', additional_number: '', short_address: '' }
}

export function nationalAddressFromApi(address: NationalAddress | null | undefined): NationalAddressForm {
  const empty = emptyNationalAddress()
  if (!address) return empty
  for (const part of NATIONAL_ADDRESS_PARTS) empty[part] = address[part] ?? ''
  return empty
}

/** Request body: trimmed, Western digits, upper-case short address; empty parts → null. */
export function nationalAddressPayload(form: NationalAddressForm): NationalAddressInput {
  const clean = (value: string) => {
    const trimmed = normalizeDigits(value).trim()
    return trimmed === '' ? null : trimmed
  }
  return {
    building_number: clean(form.building_number),
    street: clean(form.street),
    district: clean(form.district),
    postal_code: clean(form.postal_code),
    additional_number: clean(form.additional_number),
    short_address: clean(form.short_address)?.toUpperCase() ?? null,
  }
}

export type NationalAddressErrorKey = 'building_number' | 'postal_code' | 'additional_number' | 'short_address'

/**
 * Format problems of the filled parts (all parts are optional at registration). Returns the part →
 * validation message key (`validation.<key>`).
 */
export function nationalAddressFormatErrors(form: NationalAddressForm): Partial<Record<keyof NationalAddressForm, string>> {
  const errors: Partial<Record<keyof NationalAddressForm, string>> = {}
  const checks: Array<[NationalAddressErrorKey, string]> = [
    ['building_number', 'validation.building_number'],
    ['postal_code', 'validation.postal_code'],
    ['additional_number', 'validation.additional_number'],
    ['short_address', 'validation.short_address'],
  ]
  for (const [part, key] of checks) {
    const value = normalizeDigits(form[part]).trim().toUpperCase()
    if (value !== '' && !NATIONAL_ADDRESS_PATTERNS[part].test(value)) errors[part] = key
  }
  return errors
}

/** `null` for blank optional text, trimmed otherwise. */
export function optionalText(value: string | null | undefined): string | null {
  const trimmed = (value ?? '').trim()
  return trimmed === '' ? null : trimmed
}
