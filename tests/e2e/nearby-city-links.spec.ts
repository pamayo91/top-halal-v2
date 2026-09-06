import { expect, test } from '@playwright/test';

test('city pages render crawlable nearby-city links without browser failures on desktop and mobile', async ({ page }) => {
  const consoleErrors: string[] = [];
  const networkErrors: string[] = [];

  page.on('console', (message) => {
    if (message.type() === 'error' && !message.text().startsWith('Failed to load resource:')) consoleErrors.push(message.text());
  });
  page.on('requestfailed', (request) => networkErrors.push(`${request.method()} ${request.url()}`));
  page.on('response', (response) => {
    if (response.status() >= 500) networkErrors.push(`${response.status()} ${response.url()}`);
  });

  for (const slug of ['marseille', 'paris', 'lyon', 'ris-orangis']) {
    const response = await page.goto(`/restos/${slug}`);
    expect(response?.status()).toBe(200);
  }
  await page.goto('/restos/marseille');

  const section = page.locator('.nearby-cities');
  await expect(section).toBeVisible();
  await expect(section.getByRole('heading', { name: 'Villes aux alentours', exact: true })).toBeVisible();
  const links = section.getByRole('link');
  expect(await links.count()).toBeGreaterThan(0);
  expect(await links.count()).toBeLessThanOrEqual(15);
  await expect(links.first()).toHaveAttribute('href', /^https?:\/\/[^/]+\/restos\/[a-z0-9-]+$/);

  const columnCount = await section.locator('.nearby-cities-grid').evaluate((element) => getComputedStyle(element).columnCount);
  expect(columnCount).toBe((page.viewportSize()?.width ?? 0) < 760 ? '1' : '3');
  if ((page.viewportSize()?.width ?? 0) < 760) {
    expect(await page.locator('body').evaluate((element) => element.scrollWidth > element.clientWidth)).toBe(false);
  }

  expect(consoleErrors).toEqual([]);
  expect(networkErrors).toEqual([]);
});
