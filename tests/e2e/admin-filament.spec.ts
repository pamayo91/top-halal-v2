import { expect, test } from '@playwright/test';

const email = process.env.PREPROD_ADMIN_EMAIL;
const password = process.env.PREPROD_ADMIN_PASSWORD;

test.describe('Filament administration', () => {
  test.skip(!email || !password, 'PREPROD_ADMIN_EMAIL and PREPROD_ADMIN_PASSWORD are required.');

  test('admin dashboard, navigation and operational lists work without browser errors', async ({ page }) => {
    const consoleErrors: string[] = [];
    const networkErrors: string[] = [];

    page.on('console', (message) => {
      if (message.type() === 'error' && !message.text().startsWith('Failed to load resource:')) consoleErrors.push(message.text());
    });
    page.on('requestfailed', (request) => networkErrors.push(`${request.method()} ${request.url()}`));
    page.on('response', (response) => {
      if (response.status() === 404) networkErrors.push(`404 ${response.url()}`);
    });

    await page.goto('/admin');
    await page.locator('input[type="email"]').fill(email!);
    await page.locator('input[type="password"]').fill(password!);
    await page.locator('button[type="submit"]').click();

    await expect(page).toHaveURL(/\/admin$/);
    await expect(page.getByRole('heading', { name: 'Tableau de bord' })).toBeVisible();
    await expect(page.getByRole('searchbox', { name: 'Recherche globale' })).toBeVisible();
    for (const [path, heading] of [
      ['/admin/restaurants', 'Restaurants'],
      ['/admin/articles', 'Articles'],
      ['/admin/pages', 'Pages'],
      ['/admin/restaurant-reviews', 'Avis'],
      ['/admin/comments', 'Commentaires'],
      ['/admin/restaurant-claims', 'Claims'],
      ['/admin/locations', 'Géographie'],
      ['/admin/redirect-rules', null],
      ['/admin/settings', 'Réglages'],
      ['/admin/admin-audit-logs', null],
    ]) {
      const response = await page.goto(path);
      expect(response?.status()).toBe(200);
      if (heading) await expect(page.getByRole('heading', { name: heading, exact: true })).toBeVisible();
    }

    expect(consoleErrors).toEqual([]);
    expect(networkErrors).toEqual([]);
  });

  test('review and comment detail dialogs show historical dates', async ({ page }) => {
    await page.goto('/admin');
    await page.locator('input[type="email"]').fill(email!);
    await page.locator('input[type="password"]').fill(password!);
    await page.locator('button[type="submit"]').click();
    await expect(page).toHaveURL(/\/admin$/);

    for (const [path, label] of [['/admin/restaurant-reviews', 'Date historique de l’avis'], ['/admin/comments', 'Date historique du commentaire']]) {
      await page.goto(path);
      await expect(page.getByRole('table')).toContainText(/\d{1,2} [a-zéû]+ 20\d{2} à \d{2}:\d{2}/i);
      await page.getByRole('button', { name: 'Voir' }).first().click();
      await expect(page.getByText(label, { exact: true })).toBeVisible();
      await expect(page.getByLabel(label)).not.toHaveValue('');
      await page.keyboard.press('Escape');
    }
  });
});
