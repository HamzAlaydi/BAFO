import { defineConfig, devices } from '@playwright/test'

const PORT = Number(process.env.E2E_PORT ?? 3100)
const baseURL = process.env.E2E_BASE_URL ?? `http://localhost:${PORT}`

/**
 * End-to-end smoke tests. Runs against a production build (`pnpm build` first) unless
 * E2E_BASE_URL points at an already running server. Browsers: `pnpm exec playwright install chromium`,
 * or PW_CHANNEL=chrome to drive the installed Google Chrome.
 */
export default defineConfig({
  testDir: './tests/e2e',
  fullyParallel: true,
  forbidOnly: Boolean(process.env.CI),
  retries: process.env.CI ? 2 : 0,
  reporter: process.env.CI ? 'github' : 'list',
  use: {
    baseURL,
    trace: 'on-first-retry',
    ...(process.env.PW_CHANNEL ? { channel: process.env.PW_CHANNEL } : {}),
  },
  projects: [
    { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
    { name: 'mobile', use: { ...devices['Pixel 7'] } },
  ],
  webServer: process.env.E2E_BASE_URL
    ? undefined
    : {
        command: `node .output/server/index.mjs`,
        env: { PORT: String(PORT) },
        url: `${baseURL}/ar`,
        reuseExistingServer: !process.env.CI,
        timeout: 60_000,
      },
})
