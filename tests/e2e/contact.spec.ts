import { expect, test } from '@playwright/test';

test.describe('Contact public', () => {
  test('validates then accepts a contact message without browser failures', async ({ page }) => {
    const errors: string[] = [];
    page.on('console', (message) => { if (message.type() === 'error') errors.push(message.text()); });
    page.on('pageerror', (error) => errors.push(error.message));

    await page.goto('/contact');
    await expect(page.getByRole('heading', { name: 'Nous contacter' })).toBeVisible();
    await page.getByRole('button', { name: 'Envoyer le message' }).click();
    await expect(page.locator('input[name="name"]')).toBeFocused();
    await page.locator('input[name="name"]').fill('Validation Playwright');
    await page.locator('input[name="email"]').fill('invalide');
    await page.getByRole('button', { name: 'Envoyer le message' }).click();
    await expect(page.locator('input[name="email"]')).toBeFocused();
    await page.locator('input[name="email"]').fill(`validation-${Date.now()}@top-halal.fr`);
    await page.locator('input[name="subject"]').fill('Validation préproduction');
    await page.locator('textarea[name="message"]').fill('Message de validation automatisée.');
    await page.getByRole('button', { name: 'Envoyer le message' }).click();
    await expect(page.getByRole('status')).toContainText('Merci');
    expect(errors).toEqual([]);
  });
});
