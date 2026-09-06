import { expect, test } from '@playwright/test';

test('structured city pages remain usable on desktop and mobile with their administrative breadcrumb', async ({ page }) => {
  for (const [slug, city, region, department] of [
    ['marseille', 'Marseille', "Provence-Alpes-Côte d'Azur", 'Bouches-du-Rhône'],
    ['paris', 'Paris', 'Île-de-France', 'Paris'],
    ['lyon', 'Lyon', 'Auvergne-Rhône-Alpes', 'Rhône'],
    ['ris-orangis', 'Ris-Orangis', 'Île-de-France', 'Essonne'],
    ['lhay-les-roses', "L'Haÿ-les-Roses", 'Île-de-France', 'Val-de-Marne'],
  ]) {
    const response = await page.goto(`/restos/${slug}`);
    expect(response?.status()).toBe(200);
    await expect(page.getByRole('heading', { name: `Restaurants halal : ${city}` })).toBeVisible();
    await expect(page.locator('link[rel="canonical"]')).toHaveAttribute('href', new RegExp(`/restos/${slug}$`));
    await expect(page.locator('.breadcrumbs')).toContainText(region);
    await expect(page.locator('.breadcrumbs')).toContainText(department);
    await expect(page.locator('.breadcrumbs')).toContainText(city);
    const breadcrumb = await page.locator('script[type="application/ld+json"]').evaluateAll((scripts) => scripts
      .map((script) => JSON.parse(script.textContent || '{}'))
      .find((json) => json['@type'] === 'BreadcrumbList'));
    expect(breadcrumb.itemListElement.map((item: { name: string }) => item.name)).toEqual(
      slug === 'paris' ? ['Accueil', 'Restaurants', 'Île-de-France', 'Paris'] : ['Accueil', 'Restaurants', region, department, city],
    );
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
