import { expect, test } from '@playwright/test';

test('Quick map is usable', async ({ page }) => {
  const consoleErrors: string[] = [];
  const failedResponses: string[] = [];
  page.on('console', message => {
    if (message.type() === 'error') consoleErrors.push(message.text());
  });
  page.on('response', response => {
    if (response.status() >= 400) failedResponses.push(`${response.status()} ${response.url()}`);
  });

  await page.goto('/quick-hallal');
  const block = page.locator('.editorial-quick-map');
  await expect(block).toBeVisible();
  const map = block.locator('[data-quick-restaurants-map]');
  await expect(map).toHaveClass(/leaflet-container/);
  const marker = map.locator('img.quick-map-marker').first();
  await expect(marker).toBeVisible();
  await expect(marker).toHaveAttribute('src', /\/build\/assets\/quick-halal-marker-[\w-]+\.png$/);
  await expect(block.getByRole('heading', { name: 'Principales villes' })).toBeVisible();
  const cityLink = block.locator('.editorial-quick-map-cities li a').first();
  await expect(cityLink).toHaveAttribute('href', /\/restaurants\?q=Quick&city_code=\d+$/);
  await Promise.all([
    page.waitForURL(/\/restaurants\?q=Quick&city_code=\d+$/),
    cityLink.click(),
  ]);
  await expect(page.locator('meta[name="robots"]')).toHaveAttribute('content', 'noindex,follow');
  const cityResultNames = await page.locator('.restaurant-card h3 a').allTextContents();
  expect(cityResultNames.length).toBeGreaterThan(0);
  expect(cityResultNames.every(name => name.toLowerCase().includes('quick'))).toBeTruthy();

  await page.goto('/quick-hallal');
  const allQuickLink = page.getByRole('link', { name: 'Voir tous les Quick halal' });
  await expect(allQuickLink).toHaveAttribute('href', '/restaurants?q=Quick');
  await Promise.all([
    page.waitForURL('/restaurants?q=Quick'),
    allQuickLink.click(),
  ]);
  const allResultNames = await page.locator('.restaurant-card h3 a').allTextContents();
  expect(allResultNames.length).toBeGreaterThan(0);
  expect(allResultNames.every(name => name.toLowerCase().includes('quick'))).toBeTruthy();
  await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBeTruthy();
  expect(consoleErrors).toEqual([]);
  expect(failedResponses).toEqual([]);
});
