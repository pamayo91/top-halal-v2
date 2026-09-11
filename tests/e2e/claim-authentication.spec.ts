import { expect, test } from '@playwright/test';

test.describe('Authentification avant revendication', () => {
  test('explains the account requirement and resumes the selected claim after registration and login', async ({ page, context }, testInfo) => {
    const errors: string[] = [];
    page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
    page.on('pageerror', error => errors.push(error.message));

    await page.goto('/resto/01-kebab');
    const claimLink = page.getByRole('link', { name: 'Revendiquer ce restaurant' });
    await expect(claimLink).toBeVisible();
    const claimUrl = await claimLink.getAttribute('href');
    await claimLink.click();

    await expect(page.getByRole('heading', { name: 'Revendiquer ce restaurant' })).toBeVisible();
    await expect(page.locator('.claim-auth-card.contact-form-card')).toBeVisible();
    await expect(page.getByRole('link', { name: 'Se connecter' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Créer un compte' })).toBeVisible();
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    if (testInfo.project.name === 'desktop-chromium') {
      const card = await page.locator('.claim-auth-card').boundingBox();
      expect(card).not.toBeNull();
      expect(Math.abs((card!.x + card!.width / 2) - 720)).toBeLessThan(8);
    }

    const email = `claim-auth-${Date.now()}-${testInfo.project.name}@example.invalid`;
    const password = 'password-long-123';
    await page.getByRole('link', { name: 'Créer un compte' }).click();
    await page.locator('input[name="name"]').fill('Validation Claim');
    await page.locator('input[name="email"]').fill(email);
    await page.locator('input[name="password"]').fill(password);
    await page.locator('input[name="password_confirmation"]').fill(password);
    await page.getByRole('button', { name: 'Créer mon compte' }).click();
    await expect(page).toHaveURL(claimUrl!);
    await expect(page.getByText('Nom / prénom')).toBeVisible();

    await context.clearCookies();
    await page.goto(claimUrl!);
    await page.getByRole('link', { name: 'Se connecter' }).click();
    await page.locator('input[name="email"]').fill(email);
    await page.locator('input[name="password"]').fill(password);
    await page.getByRole('button', { name: 'Se connecter' }).click();
    await expect(page).toHaveURL(claimUrl!);
    await expect(page.getByText('Nom / prénom')).toBeVisible();
    expect(errors).toEqual([]);
  });
});
