import { expect, test } from '@playwright/test';

const email = process.env.PREPROD_ADMIN_EMAIL;
const password = process.env.PREPROD_ADMIN_PASSWORD;

test.describe('Adresse intelligente Filament', () => {
  test.skip(!email || !password, 'PREPROD_ADMIN_EMAIL and PREPROD_ADMIN_PASSWORD are required.');

  test('O Sha displays the operational address form and map without errors', async ({ page }) => {
    const errors: string[] = [];
    page.on('console', (message) => { if (message.type() === 'error') errors.push(message.text()); });
    await page.goto('/admin');
    await page.getByLabel(/Adresse e-mail/).fill(email!);
    await page.getByLabel(/Mot de passe/).fill(password!);
    await page.getByRole('button', { name: 'Connexion' }).click();
    await expect(page).toHaveURL(/\/admin$/);

    await page.goto('/admin/restaurants/7708/edit');
    await page.getByRole('tab', { name: 'Localisation' }).click();
    await expect(page.getByRole('combobox', { name: 'Rechercher une adresse', exact: true })).toBeVisible();
    await expect(page.getByRole('textbox', { name: 'Adresse', exact: true })).toBeVisible();
    await expect(page.getByRole('textbox', { name: 'Code INSEE', exact: true })).toBeVisible();
    await expect(page.getByText('Données d’origine')).toHaveCount(0);
    await expect(page.getByText('Qualité de localisation')).toHaveCount(0);
    await expect(page.locator('[data-top-halal-location-map]')).toBeVisible();
    await expect(page.locator('[data-top-halal-location-map] .leaflet-container')).toBeVisible();
    expect(await page.locator('[data-top-halal-location-map] .leaflet-container').evaluate((element) => element.clientWidth)).toBeGreaterThan(1);
    await expect(page.locator('[data-top-halal-location-map] .leaflet-marker-icon')).toBeVisible();
    expect(errors).toEqual([]);
  });
});
