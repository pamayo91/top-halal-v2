import { expect, test } from '@playwright/test';

const saffron = 'rgb(217, 164, 65)';

async function expectSaffronFocus(page: import('@playwright/test').Page, selector: string): Promise<void> {
  const field = page.locator(selector);
  await field.click();
  await expect.poll(() => field.evaluate((element) => ({
    border: getComputedStyle(element).borderColor,
    outline: getComputedStyle(element).outlineStyle,
  }))).toEqual({ border: saffron, outline: 'none' });
}

test('public field focus and footer divider use the saffron system accent', async ({ page }) => {
  await page.goto('/contact');
  await expectSaffronFocus(page, '#contact-name');
  await expect(page.locator('.footer-bottom')).toHaveCSS('border-top-style', 'solid');

  await page.goto('/ajouter-un-restaurant');
  await expectSaffronFocus(page, 'input[name="name"]');

  await page.goto('/login');
  await expectSaffronFocus(page, 'input[type="email"]');

  await page.goto('/magie-bonus-sans-depot-2026');
  await page.getByText('Laisser un commentaire', { exact: true }).click();
  await expectSaffronFocus(page, '.comments textarea');
});
