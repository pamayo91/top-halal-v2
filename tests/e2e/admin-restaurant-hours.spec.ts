import { expect, test } from '@playwright/test';

const email = process.env.PREPROD_ADMIN_EMAIL;
const password = process.env.PREPROD_ADMIN_PASSWORD;

test.describe('Horaires restaurant Filament', () => {
  test.skip(!email || !password, 'PREPROD_ADMIN_EMAIL and PREPROD_ADMIN_PASSWORD are required.');

  test('the restaurant editor exposes the seven-day hours tab without browser errors', async ({ page }) => {
    const errors: string[] = [];
    page.on('console', (message) => {
      if (message.type() === 'error' && !message.text().startsWith('Failed to load resource:')) errors.push(message.text());
    });
    page.on('requestfailed', (request) => errors.push(`${request.method()} ${request.url()}`));

    await page.goto('/admin');
    await page.getByLabel(/Adresse e-mail/).fill(email!);
    await page.getByLabel(/Mot de passe/).fill(password!);
    await page.getByRole('button', { name: 'Connexion' }).click();
    await expect(page).toHaveURL(/\/admin$/);

    await page.goto('/admin/restaurants/7699/edit');
    await page.getByRole('tab', { name: 'Horaires' }).click();

    const hoursSection = page.locator('.fi-section').filter({ hasText: 'Horaires d’ouverture' });
    await expect(hoursSection).toBeVisible();
    await expect(page.locator('select[id$=".day"]')).toHaveCount(0);
    await expect(page.getByText('Lundi', { exact: true })).toBeVisible();
    await expect(page.getByText('Dimanche', { exact: true })).toBeVisible();
    const statuses = page.locator('select[id*=".hours."][id$=".status"]');
    await expect(statuses).toHaveCount(7);
    await expect(statuses.first()).toHaveValue(/closed|slots|all_day/);
    await statuses.first().selectOption('slots');
    await expect(hoursSection.locator('.restaurant-hours-slots table')).toHaveCount(0);
    const slots = hoursSection.locator('.restaurant-hours-slots').first();
    await expect(slots).toBeVisible();
    await expect(slots).toHaveCSS('display', 'grid');
    await expect(slots).toHaveCSS('width', /px/);
    await expect(slots.locator('.fi-input-wrp').first()).toHaveCSS('min-height', '32px');
    await slots.getByRole('button', { name: '+ Ajouter une plage' }).click();
    const slotItems = slots.locator('.fi-fo-repeater-item');
    await expect(slotItems).toHaveCount(2);
    const [firstInput, secondInput, addButton, deleteButton] = await Promise.all([
      slotItems.nth(0).locator('.fi-input-wrp').first().boundingBox(),
      slotItems.nth(1).locator('.fi-input-wrp').first().boundingBox(),
      slots.getByRole('button', { name: '+ Ajouter une plage' }).boundingBox(),
      slotItems.nth(1).locator('.fi-fo-repeater-item-header button').boundingBox(),
    ]);
    expect(firstInput?.x).toBe(secondInput?.x);
    expect(addButton?.x).toBe(deleteButton?.x);
    expect((secondInput?.y ?? 0) - (firstInput?.y ?? 0)).toBeLessThanOrEqual(36);
    expect((await hoursSection.boundingBox())?.height).toBeLessThan(700);
    expect(errors).toEqual([]);
  });
});
