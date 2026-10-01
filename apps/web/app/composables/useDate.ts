/**
 * Locale-aware date helpers: Gregorian calendar, Arabic month names in Arabic, Latin digits,
 * Riyadh time zone (D5 in docs/02_design/R2R3_rebrand_content.md).
 */
export function useDate() {
  const locale = useAppLocale()
  const { t } = useNuxtApp().$i18n

  return {
    formatDate: (value: string | number | Date) => formatDate(value, locale.value),
    formatTime: (value: string | number | Date) => formatTime(value, locale.value),
    formatDateTime: (value: string | number | Date) => formatDateTime(value, locale.value),
    /** Deadline with the zone label, e.g. "29 سبتمبر 2026، 3:05 م بتوقيت الرياض". */
    formatDeadline: (value: string | number | Date) => `${formatDateTime(value, locale.value)} ${t('common.time.riyadh_suffix')}`,
    timeZone: RIYADH_TIME_ZONE,
  }
}
