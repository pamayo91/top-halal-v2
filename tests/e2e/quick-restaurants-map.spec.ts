import { expect, test } from '@playwright/test';

test('Quick map is usable', async ({ page }) => {
  const consoleErrors: string[] = [];
  page.on('console', message => {
    if (message.type() === 'error') consoleErrors.push(message.text());
  });

  await page.goto('/quick-hallal');
  const block = page.locator('.editorial-quick-map');
  await expect(block).toBeVisible();
  const map = block.locator('[data-quick-restaurants-map]');
  await expect(map).toHaveClass(/leaflet-container/);
  await expect(map.locator('.leaflet-marker-icon').first()).toBeVisible();
  await expect(block.getByRole('heading', { name: 'Principales villes' })).toBeVisible();
  await expect(block.getByRole('link', { name: /Voir toutes les villes/ })).toBeVisible();
  await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBeTruthy();
  expect(consoleErrors).toEqual([]);
});
