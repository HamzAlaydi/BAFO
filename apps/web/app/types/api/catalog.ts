/** Catalog lookups (API.md §1.2, §2.4). `name` is localised by `Accept-Language`. */
import type { Ulid } from './common'
import type { Direction, Format, RulesInput } from './competitions'

export interface Region {
  id: Ulid
  code: string
  name: string
}

export interface Category {
  id: Ulid
  code: string
  name: string
  /** "Other": `category_other_text` becomes required. */
  is_other: boolean
  auction_allowed: boolean
}

export type CloseReasonKind = 'cancel' | 'not_awarded' | 'award_justification' | 'void_offer'

export interface CloseReason {
  id: Ulid
  code: string
  kind: CloseReasonKind
  name: string
  requires_note: boolean
}

/** Presets carry rules without start or reserve prices. */
export type PresetRules = Omit<RulesInput, 'start_price_minor' | 'reserve_price_minor'>

export interface Preset {
  id: Ulid
  code: string
  name: string
  description: string
  direction: Direction
  format: Format
  rules: PresetRules
}

/** `GET /lookups`: active rows only, ordered by `sort_order`. */
export interface Lookups {
  regions: Region[]
  categories: Category[]
  close_reasons: CloseReason[]
  presets: Preset[]
}

export type LookupType = 'regions' | 'categories' | 'close-reasons' | 'presets'
