import { expect, test } from '@playwright/test';

test.describe('Contact public', () => {
  test('is responsive, validates and accepts a message without browser failures', async ({ page }) => {
    const errors: string[] = [];
    page.on('console', (message) => { if (message.type() === 'error') errors.push(message.text()); });
    page.on('pageerror', (error) => errors.push(error.message));

    await page.goto('/contact');
    await expect(page.getByRole('heading', { name: 'Une question ? On vous écoute.' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Envoyez-nous un message' })).toBeVisible();
    await expect(page.locator('.contact-topics li')).toHaveCount(3);
    await expect(page.locator('.contact-sticker')).toBeVisible();
    await expect(page.locator('.contact-illustration')).toHaveAttribute('src', /images\/contact\/contact-illustration\.png/);
    await expect(page.locator('.contact-count')).toHaveText('0 / 5000');
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    await expect.poll(async () => page.locator('.hp').evaluate((element) => element.getBoundingClientRect().right < 0)).toBe(true);

    await page.getByRole('button', { name: 'Envoyer mon message' }).click();
    await expect(page.locator('input[name="name"]')).toBeFocused();
    await page.locator('input[name="name"]').fill('Validation Playwright');
    await page.locator('input[name="email"]').fill('invalide');
    await page.getByRole('button', { name: 'Envoyer mon message' }).click();
    await expect(page.locator('input[name="email"]')).toBeFocused();
    await page.locator('input[name="email"]').fill(`validation-${Date.now()}@top-halal.fr`);
    await page.locator('input[name="subject"]').fill('Validation préproduction');
    await page.locator('textarea[name="message"]').fill('Message de validation automatisée.');
    await expect(page.locator('.contact-count')).toHaveText('34 / 5000');
    await page.getByRole('button', { name: 'Envoyer mon message' }).click();
    await expect(page.getByRole('status')).toContainText('Merci');
    await expect(page.getByRole('heading', { name: 'Message envoyé' })).toBeVisible();
    expect(errors).toEqual([]);
  });
});
