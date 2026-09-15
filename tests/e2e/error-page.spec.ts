import { expect, test } from '@playwright/test';

test('the public 404 is a responsive, clean HTTP 404 page', async ({ page }) => {
  const consoleErrors: string[] = [];
  const networkFailures: string[] = [];
  page.on('console', message => {
    if (message.type() === 'error' && !/Failed to load resource:.*404/i.test(message.text())) consoleErrors.push(message.text());
  });
  page.on('requestfailed', request => networkFailures.push(`${request.method()} ${request.url()}`));

  const response = await page.goto('/__top_halal_404_visual_check');
  expect(response?.status()).toBe(404);
  await expect(page.getByRole('heading', { name: "Cette page n'est plus au menu" })).toBeVisible();
  await expect(page.locator('.error-404-visual img')).toBeVisible();
  expect(await page.locator('.error-404-visual img').evaluate(element => getComputedStyle(element).objectFit)).toBe('contain');
  const homeHref = await page.getByRole('link', { name: 'Retour à l’accueil' }).getAttribute('href');
  const restaurantsHref = await page.getByRole('link', { name: 'Trouver un restaurant halal' }).getAttribute('href');
  expect(new URL(homeHref ?? '', page.url()).pathname).toBe('/');
  expect(new URL(restaurantsHref ?? '', page.url()).pathname).toBe('/restaurants');
  await expect(page.getByText('Des milliers de restaurants')).toBeVisible();
  await expect(page.getByText('Toutes vos cuisines préférées')).toBeVisible();
  await expect(page.getByText('Des avis authentiques')).toBeVisible();
  await expect(page.getByText('Une recherche en toute confiance')).toBeVisible();
  expect(await page.locator('html').evaluate(element => element.scrollWidth <= element.clientWidth)).toBe(true);
  expect(consoleErrors).toEqual([]);
  expect(networkFailures).toEqual([]);
});

test('the desktop 404 visual column matches the content block height', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto('/__top_halal_404_desktop_balance_check');

  const visual = await page.locator('.error-404-visual').boundingBox();
  const copy = await page.locator('.error-404-copy').boundingBox();
  expect(Math.abs((visual?.height ?? 0) - (copy?.height ?? 0))).toBeLessThanOrEqual(1);
});

test('the 404 stacks illustration, content and actions on mobile', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  const response = await page.goto('/__top_halal_404_mobile_check');
  expect(response?.status()).toBe(404);

  const visual = await page.locator('.error-404-visual').boundingBox();
  const copy = await page.locator('.error-404-copy').boundingBox();
  expect(visual?.y).toBeLessThan(copy?.y ?? 0);
  await expect(page.locator('.error-404-actions .button')).toHaveCount(2);
  expect(await page.locator('html').evaluate(element => element.scrollWidth <= element.clientWidth)).toBe(true);
});
