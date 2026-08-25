import { expect, test } from '@playwright/test';

test('preproduction homepage and health endpoint respond cleanly', async ({ page, request }) => {
  const consoleErrors: string[] = [];
  const networkErrors: string[] = [];

  page.on('console', (message) => {
    if (message.type() === 'error') {
      consoleErrors.push(message.text());
    }
  });
  page.on('requestfailed', (request) => {
    networkErrors.push(`${request.method()} ${request.url()} ${request.failure()?.errorText ?? ''}`);
  });

  await page.goto('/');
  await expect(page.getByRole('heading', { name: 'Top Halal V2' })).toBeVisible();

  const health = await request.get('/health');
  await expect(health).toBeOK();
  expect(await health.json()).toEqual({
    status: 'ok',
    service: 'top-halal-v2',
  });

  expect(consoleErrors).toEqual([]);
  expect(networkErrors).toEqual([]);
});

test('preproduction 404 and sensitive paths are not exposed', async ({ request }) => {
  const missing = await request.get('/__codex_missing_404');
  expect(missing.status()).toBe(404);

  for (const path of ['/.env', '/composer.json', '/artisan', '/storage/', '/vendor/', '/.git/']) {
    const response = await request.get(path, { maxRedirects: 5 });

    expect(response.status(), `${path} must not be publicly readable`).toBeGreaterThanOrEqual(400);
    expect(response.status(), `${path} must not be publicly readable`).not.toBe(200);
  }
});
