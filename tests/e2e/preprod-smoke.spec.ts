import { expect, test } from '@playwright/test';

test('preproduction public page and health endpoint respond', async ({ page, request }) => {
  const consoleErrors: string[] = [];

  page.on('console', (message) => {
    if (message.type() === 'error') {
      consoleErrors.push(message.text());
    }
  });

  await page.goto('/');
  await expect(page.getByRole('heading', { name: 'Top Halal V2' })).toBeVisible();

  const health = await request.get('/health');
  expect(health.ok()).toBeTruthy();
  await expect(health).toHaveJSON({
    status: 'ok',
    service: 'top-halal-v2',
  });

  expect(consoleErrors).toEqual([]);
});
