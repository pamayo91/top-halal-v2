import { expect, test } from '@playwright/test';

const email = process.env.PREPROD_ADMIN_EMAIL;
const password = process.env.PREPROD_ADMIN_PASSWORD;

test.describe('Refus de proposition de restaurant', () => {
  test.skip(!email || !password, 'PREPROD_ADMIN_EMAIL and PREPROD_ADMIN_PASSWORD are required.');

  test('the existing Contact form accepts only an opaque refusal reference', async ({ page }) => {
    await page.goto('/contact?reference=TH-PROP-ABCDEFGH1234');

    await expect(page.getByText('Référence de votre proposition : TH-PROP-ABCDEFGH1234')).toBeVisible();
    await expect(page.getByLabel('Sujet')).toHaveValue('Contestation d’un refus de proposition');
    await expect(page.locator('input[name="submission_reference"]')).toHaveValue('TH-PROP-ABCDEFGH1234');
  });

  test('the review queue exposes the refusal action and its optional reason without changing a record', async ({ page }) => {
    await page.goto('/admin');
    await page.locator('input[type="email"]').fill(email!);
    await page.locator('input[type="password"]').fill(password!);
    await page.locator('button[type="submit"]').click();
    await expect(page).toHaveURL(/\/admin$/);

    await page.goto('/admin/restaurants');
    const reject = page.getByRole('button', { name: 'Refuser' }).first();
    test.skip(await reject.count() === 0, 'No pending_admin_review submission is currently available for a read-only BO check.');
    await reject.click();
    await expect(page.getByRole('heading', { name: 'Refuser cette proposition ?' })).toBeVisible();
    await expect(page.getByLabel('Motif de refus')).toBeVisible();
    await page.keyboard.press('Escape');
  });
});
