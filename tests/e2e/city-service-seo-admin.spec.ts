import { expect, test } from '@playwright/test';

const email = process.env.PREPROD_ADMIN_EMAIL;
const password = process.env.PREPROD_ADMIN_PASSWORD;

test.describe('Administration des facettes SEO ville + service', () => {
  test.skip(!email || !password, 'PREPROD_ADMIN_EMAIL and PREPROD_ADMIN_PASSWORD are required.');

  test('a Marseille service can be opened and closed without a persistent default configuration', async ({ page }) => {
    await page.goto('/admin');
    await page.locator('input[type="email"]').fill(email!);
    await page.locator('input[type="password"]').fill(password!);
    await page.locator('button[type="submit"]').click();
    await expect(page).toHaveURL(/\/admin$/);

    await page.goto('/admin/facettes-seo?type=service');
    await page.getByLabel('Type de facette').selectOption('service');
    await page.getByRole('searchbox', { name: 'Rechercher', exact: true }).fill('Marseille');
    await page.waitForTimeout(500);
    const row = page.getByRole('table').getByRole('row').filter({ hasText: 'Marseille' }).filter({ hasText: 'Vente à emporter' }).first();
    await expect(row).toBeVisible();
    const href = await row.getByRole('link', { name: 'Modifier' }).getAttribute('href');
    expect(href).toBeTruthy();

    await page.goto(href!);
    const editor = page.locator('#facet-seo-editor');
    await editor.getByLabel('État*').selectOption('open');
    await page.getByRole('button', { name: 'Enregistrer' }).click();
    await expect(page.getByText('Facette SEO enregistrée', { exact: true })).toBeVisible();
    await page.goto('/restos/marseille/vente-a-emporter');
    await expect(page.getByRole('heading', { name: 'Restaurants halal avec vente à emporter à Marseille', exact: true })).toBeVisible();
    await expect(page.locator('meta[name="robots"]')).toHaveAttribute('content', 'index,follow');
    await expect(page.locator('link[rel="canonical"]')).toHaveAttribute('href', /\/restos\/marseille\/vente-a-emporter$/);

    await page.goto(href!);
    await editor.getByLabel('État*').selectOption('closed');
    await page.getByRole('button', { name: 'Enregistrer' }).click();
    await expect(page.getByText('Facette SEO enregistrée', { exact: true })).toBeVisible();
    await page.goto('/restos/marseille/vente-a-emporter');
    await expect(page.getByRole('heading', { name: 'Page introuvable', exact: true })).toBeVisible();
  });
});
