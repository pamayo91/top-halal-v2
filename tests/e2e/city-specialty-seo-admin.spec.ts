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

test.describe('Administration des facettes SEO ville + spécialité', () => {
  test.skip(!email || !password, 'PREPROD_ADMIN_EMAIL and PREPROD_ADMIN_PASSWORD are required.');

  test('a Marseille specialty can be opened, crawled, and closed again without leaving a default configuration', async ({ page }, testInfo) => {
    const specialty = testInfo.project.name === 'mobile-chromium'
      ? { name: 'Turque', slug: 'cuisine-turque' }
      : { name: 'Fast-food', slug: 'cuisine-fast-food' };
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
    await page.goto('/admin/facettes-seo');
    await expect(page.getByRole('heading', { name: 'Facettes SEO', exact: true })).toBeVisible();
    const table = page.getByRole('table');
    await page.getByRole('searchbox', { name: 'Rechercher', exact: true }).fill('Marseille');
    await page.waitForTimeout(500);
    const row = table.getByRole('row').filter({ hasText: 'Marseille' }).filter({ hasText: specialty.name }).first();
    await expect(row).toBeVisible();
    const href = await row.getByRole('link', { name: 'Modifier' }).getAttribute('href');
    expect(href).toBeTruthy();
    await page.goto(href!);

    const editor = page.locator('#facet-seo-editor');
    const state = editor.getByLabel('État*');
    await state.selectOption('open');
    await page.getByRole('button', { name: 'Enregistrer' }).click();
    await expect(page.getByText('Facette SEO enregistrée', { exact: true })).toBeVisible();
    await expect(table.getByRole('row').filter({ hasText: 'Marseille' }).filter({ hasText: specialty.name }).first().getByRole('link', { name: 'Ouverte', exact: true }))
      .toHaveAttribute('href', new RegExp(`/restos/marseille/${specialty.slug}$`));

    await page.goto(`/restos/marseille/${specialty.slug}`);
    await expect(page.getByRole('heading', { name: `Restaurants ${specialty.name} halal à Marseille`, exact: true })).toBeVisible();
    await expect(page.locator('link[rel="canonical"]')).toHaveAttribute('href', new RegExp(`/restos/marseille/${specialty.slug}$`));
    await expect(page.locator('meta[name="robots"]')).toHaveAttribute('content', 'index,follow');
    await page.goto('/sitemap.xml');
    await expect(page.locator('body')).toContainText(`/restos/marseille/${specialty.slug}`);

    await page.goto(href!);
    await state.selectOption('closed');
    await page.getByRole('button', { name: 'Enregistrer' }).click();
    await expect(page.getByText('Facette SEO enregistrée', { exact: true })).toBeVisible();
    await page.goto(`/restos/marseille/${specialty.slug}`);
    await expect(page.getByRole('heading', { name: 'Page introuvable', exact: true })).toBeVisible();
    await page.goto('/sitemap.xml');
    await expect(page.locator('body')).not.toContainText(`/restos/marseille/${specialty.slug}`);
    expect(consoleErrors).toEqual([]);
    expect(networkErrors).toEqual([]);
  });
});
