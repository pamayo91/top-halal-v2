import { expect, test } from '@playwright/test';

const email = process.env.PREPROD_ADMIN_EMAIL;
const password = process.env.PREPROD_ADMIN_PASSWORD;

async function login(page: import('@playwright/test').Page): Promise<void> {
  await page.goto('/admin');
  await page.locator('input[type="email"]').fill(email!);
  await page.locator('input[type="password"]').fill(password!);
  await page.locator('button[type="submit"]').click();
  await expect(page).toHaveURL(/\/admin$/);
}

test.describe('Administration des pages villes SEO', () => {
  test.skip(!email || !password, 'PREPROD_ADMIN_EMAIL and PREPROD_ADMIN_PASSWORD are required.');

  test('the city table is paginated and the Marseille editor opens from Modifier', async ({ page }) => {
    const consoleErrors: string[] = [];
    const networkErrors: string[] = [];

    page.on('console', (message) => {
      if (message.type() === 'error' && !message.text().startsWith('Failed to load resource:')) consoleErrors.push(message.text());
    });
    page.on('requestfailed', (request) => networkErrors.push(`${request.method()} ${request.url()}`));
    page.on('response', (response) => {
      if (response.status() >= 500) networkErrors.push(`${response.status()} ${response.url()}`);
    });

    await login(page);
    await page.goto('/admin/pages-villes-seo');

    await expect(page.getByRole('heading', { name: 'Pages villes SEO', exact: true })).toBeVisible();
    const table = page.getByRole('table');
    await expect(table).toBeVisible();
    expect(await table.locator('tbody tr').count()).toBeLessThanOrEqual(25);

    const search = page.getByRole('searchbox', { name: 'Rechercher', exact: true });
    await search.fill('Marseille');
    await page.waitForTimeout(500);
    const marseilleRow = table.getByRole('row').filter({ hasText: 'Marseille' });
    await expect(marseilleRow).toBeVisible();
    await marseilleRow.getByRole('link', { name: 'Modifier' }).click();

    await expect(page).toHaveURL(/\/admin\/pages-villes-seo\?city=Marseille/);
    const h1 = page.getByLabel('H1 personnalisé');
    await expect(h1).toBeVisible();
    await h1.fill('Restaurants halal à Marseille');
    await page.getByRole('button', { name: 'Enregistrer' }).click();
    await expect(page.getByText('Réglages enregistrés', { exact: true })).toBeVisible();
    await expect(h1).toHaveValue('Restaurants halal à Marseille');
    expect(consoleErrors).toEqual([]);
    expect(networkErrors).toEqual([]);
  });
});
