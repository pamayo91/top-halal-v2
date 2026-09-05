import { expect, test } from '@playwright/test';

test('structured city pages remain usable on desktop and mobile', async ({ page }) => {
  for (const [slug, city] of [
    ['marseille', 'Marseille'],
    ['paris', 'Paris'],
    ['lyon', 'Lyon'],
    ['ris-orangis', 'Ris-Orangis'],
    ['lhay-les-roses', "L'Haÿ-les-Roses"],
  ]) {
    const response = await page.goto(`/restos/${slug}`);
    expect(response?.status()).toBe(200);
    await expect(page.getByRole('heading', { name: `Restaurants halal : ${city}` })).toBeVisible();
    await expect(page.locator('link[rel="canonical"]')).toHaveAttribute('href', new RegExp(`/restos/${slug}$`));
    await expect(page.locator('.breadcrumbs')).toContainText(city);
  }

  if ((page.viewportSize()?.width ?? 0) < 760) {
    expect(await page.locator('body').evaluate(el => el.scrollWidth > el.clientWidth)).toBe(false);
  }
});

test('an unknown city returns the normal 404 without a server error', async ({ page }) => {
  const response = await page.goto('/restos/ville-inexistante-pour-test');
  expect(response?.status()).toBe(404);
  await expect(page.locator('meta[name="robots"]')).toHaveAttribute('content', 'noindex,follow');
});
