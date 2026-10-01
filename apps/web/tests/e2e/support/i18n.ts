import { readFileSync } from 'node:fs'
import { arabicPluralIndex } from '../../../app/utils/plural'

/**
 * The app's own messages, so the live specs select by the same visible text a person reads, in either
 * locale (`tr('ar', 'live.status.leading')`). Interpolation (`{name}`) and plural forms (`a | b | …`,
 * the Arabic six-form rule of `app/utils/plural.ts`) follow vue-i18n closely enough for UI text.
 */
export type Locale = 'ar' | 'en'

type Messages = Record<string, unknown>

const cache = new Map<Locale, Messages>()

function messages(locale: Locale): Messages {
  let loaded = cache.get(locale)
  if (!loaded) {
    const path = new URL(`../../../i18n/locales/${locale}.json`, import.meta.url)
    loaded = JSON.parse(readFileSync(path, 'utf8')) as Messages
    cache.set(locale, loaded)
  }
  return loaded
}

function lookup(locale: Locale, key: string): string {
  const value = key.split('.').reduce<unknown>((node, part) => (node && typeof node === 'object' ? (node as Messages)[part] : undefined), messages(locale))
  if (typeof value !== 'string') throw new Error(`Missing ${locale} message "${key}"`)
  return value
}

function pluralIndex(locale: Locale, count: number, forms: number): number {
  if (locale === 'ar') return arabicPluralIndex(count, forms)
  const n = Math.abs(count)
  if (forms === 2) return n === 1 ? 0 : 1
  return n === 0 ? 0 : Math.min(n, 2)
}

/** A message with `{params}` filled in; pass `count` for plural messages. */
export function tr(locale: Locale, key: string, params: Record<string, string | number> = {}, count?: number): string {
  let message = lookup(locale, key)
  if (count !== undefined && message.includes(' | ')) {
    const forms = message.split(' | ')
    message = forms[Math.min(pluralIndex(locale, count, forms.length), forms.length - 1)] ?? message
  }
  const values: Record<string, string | number> = count === undefined ? params : { count, n: count, ...params }
  return message
    .replace(/\{'([^']*)'\}/g, '$1')
    .replace(/\{(\w+)\}/g, (match, name: string) => (name in values ? String(values[name]) : match))
}

/** The text of a message before its first `{param}`: a stable prefix for messages with dynamic parts. */
export function trPrefix(locale: Locale, key: string): string {
  const message = lookup(locale, key)
  const index = message.indexOf('{')
  return (index === -1 ? message : message.slice(0, index)).trim()
}

/** A message as a RegExp, each `{param}` matching any text (for values computed by the app). */
export function trPattern(locale: Locale, key: string): RegExp {
  return new RegExp(lookup(locale, key).split(/\{\w+\}/).map(escape).join('.+?'))
}

function escape(text: string): string {
  return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

/** Escapes a message for use inside a RegExp. */
export function rx(text: string): RegExp {
  return new RegExp(escape(text))
}

/** A field's accessible name: its label, optionally followed by the «(مطلوب)» / "(required)" hint. */
export function labelRx(label: string): RegExp {
  return new RegExp(`^${escape(label)}\\s*(\\(.*\\))?$`)
}
