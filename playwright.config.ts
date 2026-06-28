import { defineConfig, devices } from '@playwright/test';

// e2e PRODUIT (Phase 8a) contre la stack RÉELLE (compose.e2e.yaml : app+ext PECL,
// Postgres isolé, hub Mercure co-localisé, RELAIS outbox actif). Le déterminisme
// temporel vient de 3 instances d'app à APP_FAKE_NOW fixe (un vendredi avant 14:00,
// un après 14:00, un non-vendredi) — sélectionnées par « project » Playwright.
//
// Lancer en local :  npm run e2e:up && npm run e2e && npm run e2e:down
const FRIDAY_URL = process.env.E2E_FRIDAY_URL ?? 'http://localhost:8081';
const AFTERNOON_URL = process.env.E2E_AFTERNOON_URL ?? 'http://localhost:8082';
const DORMANT_URL = process.env.E2E_DORMANT_URL ?? 'http://localhost:8083';

export default defineConfig({
  testDir: './tests/e2e',
  globalSetup: './tests/e2e/global-setup.ts',
  timeout: 30_000,
  expect: { timeout: 10_000 },
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  workers: 1,
  reporter: process.env.CI ? [['list'], ['html', { open: 'never' }]] : 'list',
  use: {
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    ignoreHTTPSErrors: true,
  },
  projects: [
    {
      name: 'friday',
      testMatch: /.*\.friday\.spec\.ts/,
      use: { ...devices['Desktop Chrome'], baseURL: FRIDAY_URL },
    },
    {
      name: 'afternoon',
      testMatch: /.*\.afternoon\.spec\.ts/,
      use: { ...devices['Desktop Chrome'], baseURL: AFTERNOON_URL },
    },
    {
      name: 'dormant',
      testMatch: /.*\.dormant\.spec\.ts/,
      use: { ...devices['Desktop Chrome'], baseURL: DORMANT_URL },
    },
  ],
});
