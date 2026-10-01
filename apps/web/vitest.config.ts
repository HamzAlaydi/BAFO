import { fileURLToPath } from 'node:url'
import { defineConfig } from 'vitest/config'
import { defineVitestProject } from '@nuxt/test-utils/config'

const appDir = fileURLToPath(new URL('./app', import.meta.url))

export default defineConfig({
  test: {
    projects: [
      {
        // Pure TypeScript (utils, formatting, parsing): fast, no Nuxt runtime.
        resolve: { alias: { '~': appDir } },
        test: {
          name: 'unit',
          include: ['tests/unit/**/*.spec.ts'],
          environment: 'node',
        },
      },
      // Components, composables and stores inside a real Nuxt runtime (i18n, Pinia, auto-imports).
      await defineVitestProject({
        test: {
          name: 'nuxt',
          include: ['tests/nuxt/**/*.spec.ts'],
          environment: 'nuxt',
          environmentOptions: {
            nuxt: {
              domEnvironment: 'happy-dom',
              // Services are mocked per test. A reserved `.test` host never resolves, so app-start
              // requests (app config) cannot reach a local API and leak into the results (for example
              // a `server_time` sample moving the countdowns).
              overrides: {
                runtimeConfig: {
                  public: {
                    apiBase: 'http://api.bafo.test/api/app/v1',
                    broadcastAuthEndpoint: 'http://api.bafo.test/broadcasting/auth',
                  },
                },
              },
            },
          },
        },
      }),
    ],
  },
})
