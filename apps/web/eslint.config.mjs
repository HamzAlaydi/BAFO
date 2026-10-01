// @ts-check
import withNuxt from './.nuxt/eslint.config.mjs'

export default withNuxt({
  rules: {
    // Every user-facing string lives in i18n/locales; flag raw text in templates.
    'vue/no-bare-strings-in-template': ['error', {
      allowlist: ['—', '…', '*', '·', '(', ')', ':', '--:--:--', '0.00'],
    }],
  },
})
