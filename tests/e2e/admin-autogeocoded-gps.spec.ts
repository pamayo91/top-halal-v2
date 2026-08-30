import { expect, test } from '@playwright/test';

const email = process.env.PREPROD_ADMIN_EMAIL;
const password = process.env.PREPROD_ADMIN_PASSWORD;

test.describe('GPS auto-géocodés Filament', () => {
  test.skip(!email || !password, 'PREPROD_ADMIN_EMAIL and PREPROD_ADMIN_PASSWORD are required.');

  test('Fresh Burritos and an automatically geocoded restaurant centre the marker on stored coordinates', async ({ page }) => {
    const errors: string[] = [];
    page.on('console', (message) => { if (message.type() === 'error') errors.push(message.text()); });
    await page.goto('/admin');
    await page.getByLabel(/Adresse e-mail/).fill(email!);
    await page.getByLabel(/Mot de passe/).fill(password!);
    await page.getByRole('button', { name: 'Connexion' }).click();

    for (const [id, latitude, longitude] of [[7641, '50.6372843', '3.0747992'], [7698, '48.8819730', '2.4969610']]) {
      await page.goto(`/admin/restaurants/${id}/edit`);
      await page.getByRole('tab', { name: 'Localisation' }).click();
      await expect(page.getByRole('spinbutton', { name: 'Latitude', exact: true })).toHaveValue(latitude);
      await expect(page.getByRole('spinbutton', { name: 'Longitude', exact: true })).toHaveValue(longitude);
      await expect(page.locator('[data-top-halal-location-map] .leaflet-marker-icon')).toBeVisible();
    }
    expect(errors).toEqual([]);
  });
});
