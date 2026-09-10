import { expect, test } from '@playwright/test';

const email = process.env.PREPROD_ADMIN_EMAIL;
const password = process.env.PREPROD_ADMIN_PASSWORD;

test.describe('Historique des e-mails', () => {
  test.skip(!email || !password, 'PREPROD_ADMIN_EMAIL and PREPROD_ADMIN_PASSWORD are required.');

  test('renders French operational history, counters, filters and safe details', async ({ page }) => {
    const consoleErrors: string[] = [];
    const failedRequests: string[] = [];
    page.on('console', (message) => {
      if (message.type() === 'error' && !message.text().startsWith('Failed to load resource:')) consoleErrors.push(message.text());
    });
    page.on('requestfailed', (request) => failedRequests.push(`${request.method()} ${request.url()}`));

    await page.goto('/admin');
    await page.locator('input[type="email"]').fill(email!);
    await page.locator('input[type="password"]').fill(password!);
    await page.locator('button[type="submit"]').click();
    await expect(page).toHaveURL(/\/admin$/);

    const response = await page.goto('/admin/email-delivery-logs');
    expect(response?.status()).toBe(200);
    await expect(page.getByRole('heading', { name: 'Historique des e-mails', exact: true })).toBeVisible();
    await expect(page.getByText('En attente', { exact: true }).first()).toBeVisible();
    await expect(page.getByText('Envoyés', { exact: true })).toBeVisible();
    await expect(page.getByText('Échecs', { exact: true })).toBeVisible();
    await expect(page.getByText('Total aujourd’hui', { exact: true })).toBeVisible();
    await expect(page.getByRole('table')).toContainText('Expiré');

    await page.getByRole('button', { name: /Filtre|Filtres/ }).click();
    await expect(page.getByText('Statut', { exact: true }).last()).toBeVisible();
    await expect(page.getByText('Type', { exact: true }).last()).toBeVisible();
    await expect(page.getByRole('textbox', { name: 'Du' })).toBeVisible();
    await expect(page.getByRole('textbox', { name: 'Au' })).toBeVisible();

    await page.getByRole('button', { name: 'Voir les détails' }).first().click();
    const dialog = page.getByRole('dialog');
    await expect(dialog).toContainText('Détail de l’e-mail');
    await expect(dialog).toContainText('Destinataire');
    await expect(dialog).toContainText('Tentatives');
    await expect(dialog).toContainText('Message-ID');

    expect(consoleErrors).toEqual([]);
    expect(failedRequests).toEqual([]);
  });
});
