import { arabicPluralIndex } from '../app/utils/plural'

export default defineI18nConfig(() => ({
  fallbackLocale: 'ar',
  pluralRules: { ar: arabicPluralIndex },
  // Missing keys are a bug: surface them in development only.
  missingWarn: import.meta.dev,
  fallbackWarn: false,
}))
