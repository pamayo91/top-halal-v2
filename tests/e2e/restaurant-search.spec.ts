import { expect, test } from '@playwright/test';

test('two-field restaurant search works responsively', async ({ page }) => {
    await page.goto('/');
    const search = page.locator('[data-restaurant-search]').first();
    await expect(search.getByLabel('Localisation')).toHaveValue('Paris');
    await search.getByLabel('Localisation').focus();
    await expect(search.getByRole('button', { name: 'Autour de moi' })).toBeVisible();
    await search.getByLabel('Spécialité ou nom de restaurant').fill('burger');
    await expect(search.locator('[data-suggestions-list]')).toBeVisible();
    if ((page.viewportSize()?.width ?? 0) < 760) {
      expect(await search.evaluate(el => getComputedStyle(el).gridTemplateColumns.split(' ').length)).toBe(1);
      expect(await page.locator('body').evaluate(el => el.scrollWidth > el.clientWidth)).toBe(false);
    }
  });

test('near me is requested only after the voluntary choice and keeps the search usable on refusal', async ({ page }) => {
  await page.goto('/');
  const search = page.locator('[data-restaurant-search]').first();
  await expect(search.getByLabel('Localisation')).toHaveValue('Paris');
  await search.getByLabel('Localisation').focus();
  await page.context().grantPermissions([]);
  await search.getByRole('button', { name: 'Autour de moi' }).click();
  await expect(search.getByText('Impossible d’obtenir votre position. Choisissez une ville.')).toBeVisible();
  await expect(search.getByLabel('Localisation')).toBeFocused();
});

test('near me sends mocked coordinates only after the voluntary choice', async ({ page, context }) => {
  await context.grantPermissions(['geolocation']);
  await context.setGeolocation({ latitude: 48.8566, longitude: 2.3522 });
  await page.goto('/');
  const search = page.locator('[data-restaurant-search]').first();
  await search.getByLabel('Localisation').focus();
  await search.getByRole('button', { name: 'Autour de moi' }).click();
  await page.waitForURL(/\/restaurants\?.*lat=48\.8566.*lng=2\.3522/);
});
