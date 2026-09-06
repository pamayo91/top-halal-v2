import { expect, test } from '@playwright/test';

async function breadcrumbNames(page: import('@playwright/test').Page): Promise<string[]> {
  return page.locator('script[type="application/ld+json"]').evaluateAll((scripts) => scripts
    .map((script) => JSON.parse(script.textContent || '{}'))
    .find((json) => json['@type'] === 'BreadcrumbList')
    .itemListElement.map((item: { name: string }) => item.name));
}

test('department and region pages use short URLs, pagination, SEO directives and administrative breadcrumbs', async ({ page }) => {
  for (const [slug, heading, breadcrumb] of [
    ['bouches-du-rhone', 'Bouches-du-Rhône', ['Accueil', 'Restaurants', "Provence-Alpes-Côte d'Azur", 'Bouches-du-Rhône']],
    ['essonne', 'Essonne', ['Accueil', 'Restaurants', 'Île-de-France', 'Essonne']],
    ['rhone', 'Rhône', ['Accueil', 'Restaurants', 'Auvergne-Rhône-Alpes', 'Rhône']],
    ['provence-alpes-cote-d-azur', "Provence-Alpes-Côte d'Azur", ['Accueil', 'Restaurants', "Provence-Alpes-Côte d'Azur"]],
    ['ile-de-france', 'Île-de-France', ['Accueil', 'Restaurants', 'Île-de-France']],
    ['auvergne-rhone-alpes', 'Auvergne-Rhône-Alpes', ['Accueil', 'Restaurants', 'Auvergne-Rhône-Alpes']],
  ]) {
    const response = await page.goto(`/restos/${slug}`);
    expect(response?.status()).toBe(200);
    await expect(page.getByRole('heading', { name: `Restaurants halal : ${heading}` })).toBeVisible();
    await expect(page.locator('link[rel="canonical"]')).toHaveAttribute('href', new RegExp(`/restos/${slug}$`));
    expect(await breadcrumbNames(page)).toEqual(breadcrumb);
  }

  const pagination = await page.goto('/restos/bouches-du-rhone?page=2');
  expect(pagination?.status()).toBe(200);
  await expect(page.locator('meta[name="robots"]')).toHaveAttribute('content', 'noindex,follow');
});

test('Paris remains a single URL and Corsica and DOM pages stay usable', async ({ page }) => {
  await page.goto('/restos/paris');
  expect(await breadcrumbNames(page)).toEqual(['Accueil', 'Restaurants', 'Île-de-France', 'Paris']);

  for (const slug of ['corse', 'guadeloupe']) {
    const response = await page.goto(`/restos/${slug}`);
    expect(response?.status()).toBe(200);
    await expect(page.locator('script[type="application/ld+json"]')).toHaveCount(1);
  }

  const sitemap = await page.request.get('/sitemap.xml');
  expect(sitemap.status()).toBe(200);
  expect((await sitemap.text()).match(/<loc>[^<]*\/restos\/paris<\/loc>/g)?.length).toBe(1);
});

test('a department with a distinct homonymous city uses its code suffix', async ({ page }) => {
  for (const [citySlug, departmentSlug] of [
    ['indre', 'indre-36'],
    ['mayenne', 'mayenne-53'],
    ['vienne', 'vienne-86'],
  ]) {
    const city = await page.goto(`/restos/${citySlug}`);
    expect(city?.status()).toBe(200);
    await expect(page.locator('link[rel="canonical"]')).toHaveAttribute('href', new RegExp(`/restos/${citySlug}$`));

    const department = await page.goto(`/restos/${departmentSlug}`);
    expect(department?.status()).toBe(200);
    await expect(page.locator('link[rel="canonical"]')).toHaveAttribute('href', new RegExp(`/restos/${departmentSlug}$`));
  }

  const sitemap = await page.request.get('/sitemap.xml');
  const body = await sitemap.text();
  for (const slug of ['indre-36', 'mayenne-53', 'vienne-86']) {
    expect(body).toContain(`/restos/${slug}</loc>`);
  }
});
