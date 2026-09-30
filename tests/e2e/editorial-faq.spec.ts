import { expect, test } from '@playwright/test';

const quickPage = '/quick-hallal';

test('Quick halal FAQ is a compact native accordion without horizontal overflow', async ({ page }) => {
  await page.goto(quickPage);

  const faq = page.locator('.editorial-faq');
  await expect(faq).toBeVisible();
  const first = faq.locator('details').first();
  const summary = first.locator('summary');
  await expect(first).not.toHaveAttribute('open', '');

  await summary.click();
  await expect(first).toHaveAttribute('open', '');
  await expect(first.locator('.editorial-faq-answer')).toBeVisible();
  await summary.click();
  await expect(first).not.toHaveAttribute('open', '');

  await summary.focus();
  await page.keyboard.press('Enter');
  await expect(first).toHaveAttribute('open', '');
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBeTruthy();
});
