import { expect, test } from '@playwright/test';

test.describe('Authentification avant revendication', () => {
  test('shows the direct first-claim form without generic registration', async ({ page }, testInfo) => {
    const errors: string[] = [];
    page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
    page.on('pageerror', error => errors.push(error.message));

    await page.goto('/resto/01-kebab');
    const claimLink = page.getByRole('link', { name: 'Revendiquer ce restaurant' });
    await expect(claimLink).toBeVisible();
    await claimLink.click();

    await expect(page.getByRole('heading', { name: /Revendiquer/ })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Première revendication ?' })).toBeVisible();
    await expect(page.locator('input[name="full_name"]')).toBeVisible();
    await expect(page.getByRole('link', { name: 'Créer un compte' })).toHaveCount(0);
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    if (testInfo.project.name === 'desktop-chromium') {
      const card = await page.locator('.claim-card').first().boundingBox();
      expect(card).not.toBeNull();
      expect(Math.abs((card!.x + card!.width / 2) - 720)).toBeLessThan(8);
    }

    await expect(page.locator('form[action$="/login"]')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Envoyer ma demande' })).toBeVisible();
    expect(errors).toEqual([]);
  });
});
