const ARABIC_PLURAL_ORDER: Intl.LDMLPluralRule[] = ['zero', 'one', 'two', 'few', 'many', 'other']
const arabicPlurals = new Intl.PluralRules('ar')

/** vue-i18n's default rule, for messages with fewer than six forms. */
function defaultPluralIndex(choice: number, choicesLength: number): number {
  const n = Math.abs(choice)
  if (choicesLength === 2) return n === 1 ? 0 : 1
  return n === 0 ? 0 : Math.min(n, 2)
}

/**
 * vue-i18n plural rule for Arabic. Messages written with six forms
 * ("zero | one | two | few | many | other") follow CLDR:
 * 0 → zero, 1 → one, 2 → two, 3–10 → few, 11–99 → many, 100+ → other.
 */
export function arabicPluralIndex(choice: number, choicesLength: number): number {
  if (choicesLength !== ARABIC_PLURAL_ORDER.length) return defaultPluralIndex(choice, choicesLength)
  return ARABIC_PLURAL_ORDER.indexOf(arabicPlurals.select(Math.abs(choice)))
}
