import { expect, test } from '@playwright/test';

type BreadcrumbItem = { name: string; item: string };
type GeographicCase = {
  citySlug: string;
  region: { name: string; slug: string };
  department?: { name: string; slug: string };
  city: string;
};

const cases: GeographicCase[] = [
  { citySlug: 'marseille', region: { name: "Provence-Alpes-Côte d'Azur", slug: 'provence-alpes-cote-d-azur' }, department: { name: 'Bouches-du-Rhône', slug: 'bouches-du-rhone' }, city: 'Marseille' },
  { citySlug: 'paris', region: { name: 'Île-de-France', slug: 'ile-de-france' }, city: 'Paris' },
  { citySlug: 'lyon', region: { name: 'Auvergne-Rhône-Alpes', slug: 'auvergne-rhone-alpes' }, department: { name: 'Rhône', slug: 'rhone' }, city: 'Lyon' },
  { citySlug: 'ris-orangis', region: { name: 'Île-de-France', slug: 'ile-de-france' }, department: { name: 'Essonne', slug: 'essonne' }, city: 'Ris-Orangis' },
  { citySlug: 'saint-denis-93', region: { name: 'Île-de-France', slug: 'ile-de-france' }, department: { name: 'Seine-Saint-Denis', slug: 'seine-saint-denis' }, city: 'Saint-Denis' },
];

async function breadcrumb(page: import('@playwright/test').Page): Promise<BreadcrumbItem[]> {
  return page.locator('script[type="application/ld+json"]').evaluateAll((scripts) => scripts
    .map((script) => JSON.parse(script.textContent || '{}'))
    .find((json) => json['@type'] === 'BreadcrumbList')
    .itemListElement);
}

async function assertRestaurantBreadcrumb(
  page: import('@playwright/test').Page,
  geographicCase: GeographicCase,
): Promise<void> {
  const { citySlug, region, department, city } = geographicCase;
  const cityResponse = await page.goto(`/restos/${citySlug}`);
  expect(cityResponse?.status()).toBe(200);

  const restaurantHref = await page.locator('.restaurant-card h3 a').first().getAttribute('href');
  expect(new URL(restaurantHref!).pathname).toMatch(/^\/resto\//);

  const restaurantResponse = await page.goto(restaurantHref!);
  expect(restaurantResponse?.status()).toBe(200);
  const restaurantPath = new URL(page.url()).pathname;
  const expectedNames = ['Accueil', 'Restaurants', region.name, ...(department ? [department.name] : []), city, await page.locator('h1').innerText()];
  const expectedPaths = ['/', '/restaurants', `/restos/${region.slug}`, ...(department ? [`/restos/${department.slug}`] : []), `/restos/${citySlug}`, restaurantPath];
  const data = await breadcrumb(page);

  expect(data.map((item) => item.name)).toEqual(expectedNames);
  expect(data.map((item) => new URL(item.item).pathname)).toEqual(expectedPaths);
  expect(new Set(data.map((item) => item.name)).size).toBe(data.length);
  await expect(page.locator('.breadcrumbs [aria-current="page"]')).toHaveText(expectedNames.at(-1)!);
  await expect(page.locator('link[rel="canonical"]')).toHaveAttribute('href', new RegExp(`${restaurantPath}$`));

  if (citySlug === 'saint-denis-93') {
    await expect(page.locator('.breadcrumbs a').filter({ hasText: 'Saint-Denis' })).toHaveAttribute('href', /\/restos\/saint-denis-93$/);
  }
}

test('restaurant breadcrumbs use the published administrative hierarchy on desktop and mobile', async ({ page }) => {
  const consoleErrors: string[] = [];
  const networkFailures: string[] = [];
  const serverErrors: string[] = [];
  page.on('console', (message) => {
    if (message.type() === 'error') consoleErrors.push(message.text());
  });
  page.on('requestfailed', (request) => networkFailures.push(`${request.method()} ${request.url()}`));
  page.on('response', (response) => {
    if (response.status() >= 500) serverErrors.push(`${response.status()} ${response.url()}`);
  });

  for (const geographicCase of cases) {
    await assertRestaurantBreadcrumb(page, geographicCase);
  }

  const domResponse = await page.goto('/restos/pointe-a-pitre');
  if (domResponse?.status() === 200) {
    await assertRestaurantBreadcrumb(page, {
      citySlug: 'pointe-a-pitre',
      region: { name: 'Guadeloupe', slug: 'guadeloupe' },
      city: 'Pointe-à-Pitre',
    });
  }

  expect(consoleErrors).toEqual([]);
  expect(networkFailures).toEqual([]);
  expect(serverErrors).toEqual([]);
  if ((page.viewportSize()?.width ?? 0) < 760) {
    expect(await page.locator('body').evaluate((element) => element.scrollWidth > element.clientWidth)).toBe(false);
  }
});
