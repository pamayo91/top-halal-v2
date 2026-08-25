import { defineConfig } from '@playwright/test';

const baseURL = process.env.PREPROD_BASE_URL ?? 'http://localhost:8000';

export default defineConfig({
  testDir: './tests/e2e',
  timeout: 30_000,
  use: {
    baseURL,
    trace: 'retain-on-failure',
  },
  reporter: [['list'], ['html', { open: 'never' }]],
});
