/** Locale-aware money helpers; amounts are integer halalas (`amount_minor`). */
export function useMoney() {
  const locale = useAppLocale()

  return {
    /** 1250050 → "12,500.50 ر.س" / "SAR 12,500.50" */
    format: (minor: number) => formatMoney(minor, locale.value),
    /** 1250050 → "12,500.50" */
    formatAmount: (minor: number) => formatAmount(minor),
    currency: computed(() => currencyLabel(locale.value)),
    parse: parseAmountToMinor,
    vatOf,
    withVat,
  }
}
