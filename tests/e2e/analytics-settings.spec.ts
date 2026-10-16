import { expect, test } from '@playwright/test';

const email = process.env.PREPROD_ADMIN_EMAIL;
const password = process.env.PREPROD_ADMIN_PASSWORD;

test.describe('Réglages Google Analytics', () => {
  test.skip(!email || !password, 'PREPROD_ADMIN_EMAIL and PREPROD_ADMIN_PASSWORD are required.');

  test('configuration GA4 validates, persists, restores and does not affect the public frontend', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-chromium', 'This mutable back-office check runs once on desktop.');

    const consoleErrors: string[] = [];
    const failedRequests: string[] = [];
    const requests: string[] = [];
    page.on('console', message => { if (message.type() === 'error') consoleErrors.push(message.text()); });
    page.on('requestfailed', request => failedRequests.push(`${request.method()} ${request.url()}`));
    page.on('request', request => requests.push(request.url()));

    await page.goto('/admin');
    await page.getByLabel(/Adresse e-mail/).fill(email!);
    await page.getByLabel(/Mot de passe/).fill(password!);
    await page.getByRole('button', { name: 'Connexion' }).click();
    await expect(page).toHaveURL(/\/admin$/);
    await page.goto('/admin/settings');

    const enabled = page.getByRole('checkbox', { name: 'Activer Google Analytics' });
    const measurementId = page.getByRole('textbox', { name: 'ID de mesure GA4' });
    const save = page.getByRole('button', { name: 'Enregistrer', exact: true });
    const originalEnabled = await enabled.isChecked();
    const originalMeasurementId = await measurementId.inputValue();

    try {
      await enabled.setChecked(true);
      await measurementId.fill('UA-12345-6');
      await save.click();
      await expect(page.getByText('Saisissez un ID de mesure GA4 valide, au format G-XXXXXXXXXX.')).toBeVisible();

      await measurementId.fill(' g-ab12cd34ef ');
      await save.click();
      await expect(page.getByText('Réglages enregistrés', { exact: true })).toBeVisible();
      await page.reload();
      await expect(enabled).toBeChecked();
      await expect(measurementId).toHaveValue('G-AB12CD34EF');

      await measurementId.fill('G-ZY98XW76VU');
      await save.click();
      await expect(page.getByText('Réglages enregistrés', { exact: true })).toBeVisible();
      await page.reload();
      await expect(measurementId).toHaveValue('G-ZY98XW76VU');

      await enabled.setChecked(false);
      await measurementId.fill('');
      await save.click();
      await expect(page.getByText('Réglages enregistrés', { exact: true })).toBeVisible();
      await page.reload();
      await expect(enabled).not.toBeChecked();
      await expect(measurementId).toHaveValue('');
    } finally {
      await enabled.setChecked(originalEnabled);
      await measurementId.fill(originalMeasurementId);
      await save.click();
      await expect(page.getByText('Réglages enregistrés', { exact: true })).toBeVisible();
    }

    await page.goto('/');
    await expect(page.getByRole('heading', { name: 'Trouvez votre restaurant halal, simplement.' })).toBeVisible();
    expect(await page.content()).not.toMatch(/googletagmanager\.com|google-analytics\.com|dataLayer|G-[A-Z0-9]{4,32}/i);
    expect(requests.filter(url => /googletagmanager\.com|google-analytics\.com/i.test(url))).toEqual([]);
    expect(consoleErrors).toEqual([]);
    expect(failedRequests).toEqual([]);
  });
});
