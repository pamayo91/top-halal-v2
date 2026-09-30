import { expect, test } from '@playwright/test';

for (const viewport of [
  { name: 'desktop', width: 1440, height: 1000 },
  { name: 'mobile', width: 390, height: 844 },
]) {
  test(`Quick map is usable on ${viewport.name}`, async ({ page }) => {
    await page.setViewportSize({ width: viewport.width, height: viewport.height });
    const consoleErrors: string[] = [];
    page.on('console', message => {
      if (message.type() === 'error') consoleErrors.push(message.text());
    });

    await page.goto('/quick-hallal');
    const block = page.locator('.editorial-quick-map');
    await expect(block).toBeVisible();
    await expect(block.locator('[data-quick-restaurants-map]')).toBeVisible();
    await expect(block.getByRole('heading', { name: 'Principales villes' })).toBeVisible();
    await expect(block.getByRole('link', { name: /Voir toutes les villes/ })).toBeVisible();
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBeTruthy();
    expect(consoleErrors).toEqual([]);
  });
}
