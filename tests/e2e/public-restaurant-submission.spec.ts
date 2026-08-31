import { expect, test } from '@playwright/test';

const cover = {
  name: 'couverture.png',
  mimeType: 'image/png',
  buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', 'base64'),
};

async function fillRestaurantAndAddress(page: import('@playwright/test').Page, suffix: string) {
  await page.goto('/ajouter-un-restaurant');
  await page.locator('[data-restaurant-name]').fill(`Restaurant validation ${suffix}`);
  await page.getByLabel('Viande halal').check();
  await page.getByRole('button', { name: 'Continuer' }).click();
  await expect(page.getByRole('heading', { name: 'L’adresse' })).toBeVisible();
  await page.getByLabel('Rechercher une adresse').fill('46 Boulevard du Temple Paris');
  await expect(page.locator('[data-address-results] button').first()).toBeVisible();
  await page.locator('[data-address-results] button').first().click();
  await expect(page.locator('[data-address-selected]')).toBeVisible();
  await page.getByRole('button', { name: 'Continuer' }).click();
  await expect(page.getByRole('heading', { name: 'Les informations utiles' })).toBeVisible();
  await page.getByRole('button', { name: 'Continuer' }).click();
  await expect(page.getByRole('heading', { name: 'Les photos' })).toBeVisible();
}

test('public restaurant contribution blocks an empty halal choice and identifies a duplicate', async ({ page }) => {
  await page.goto('/ajouter-un-restaurant');
  await page.locator('[data-restaurant-name]').fill('O Sha');
  await page.getByRole('button', { name: 'Continuer' }).click();
  await expect(page.locator('[data-halal-error]')).toBeVisible();

  await page.getByLabel('Viande halal').check();
  await expect(page.locator('[data-name-duplicates]')).toContainText('O Sha');
  await expect(page.locator('[data-name-duplicates]').getByRole('link', { name: /O Sha/ })).toBeVisible();
});

test('public restaurant contribution requires a cover photo and validates the email', async ({ page }, testInfo) => {
  await fillRestaurantAndAddress(page, `photos-${testInfo.project.name}-${Date.now()}`);
  await page.getByRole('button', { name: 'Continuer' }).click();
  await expect.poll(() => page.locator('[data-cover-input]').evaluate((input: HTMLInputElement) => input.validationMessage)).not.toBe('');

  await page.locator('[data-cover-input]').setInputFiles(cover);
  await page.getByRole('button', { name: 'Continuer' }).click();
  await expect(page.getByRole('heading', { name: 'Vérification' })).toBeVisible();
  await page.getByLabel('Je suis client').check();
  await page.getByLabel('Votre e-mail').fill('email-invalide');
  await page.getByRole('button', { name: 'Envoyer le restaurant' }).click();
  await expect.poll(() => page.getByLabel('Votre e-mail').evaluate((input: HTMLInputElement) => input.validationMessage)).not.toBe('');
});

test('public restaurant contribution submits a pending restaurant successfully', async ({ page }, testInfo) => {
  const errors: string[] = [];
  page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
  await fillRestaurantAndAddress(page, `complete-${testInfo.project.name}-${Date.now()}`);
  await page.locator('[data-cover-input]').setInputFiles(cover);
  await expect(page.locator('[data-cover-preview] img')).toBeVisible();
  await page.getByRole('button', { name: 'Continuer' }).click();
  await page.getByLabel('Je suis client').check();
  await page.getByLabel('Votre e-mail').fill(`contribution-${testInfo.project.name}@example.invalid`);
  await page.getByRole('button', { name: 'Envoyer le restaurant' }).click();
  await expect(page.getByRole('heading', { name: 'Merci pour votre aide !' })).toBeVisible();
  expect(errors).toEqual([]);
});
