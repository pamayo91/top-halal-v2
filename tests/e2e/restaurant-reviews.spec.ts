import { expect, test } from '@playwright/test';

test('restaurant review preview is moderated and rejects URLs', async ({ page }) => {
  const errors: string[] = []; page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
  await page.goto('/_preview/restaurant/13567'); await expect(page.locator('h1')).toBeVisible(); await expect(page.locator('[data-review-aggregate]')).toContainText('avis');
  await page.locator('input[name="name"]').fill('Codex review'); await page.locator('input[name="email"]').fill('codex-review-test@example.invalid'); await page.locator('input[name="rating"]').fill('5'); await page.locator('textarea[name="content"]').fill('Validation sans lien.'); await page.getByRole('button',{name:'Envoyer'}).click(); await expect(page.getByRole('status')).toContainText('attente');
  await page.locator('input[name="name"]').fill('Codex review'); await page.locator('input[name="email"]').fill('codex-review-test@example.invalid'); await page.locator('input[name="rating"]').fill('5'); await page.locator('textarea[name="content"]').fill('https://example.invalid'); await page.getByRole('button',{name:'Envoyer'}).click(); await expect(page.getByRole('alert')).toContainText('liens et URLs'); expect(errors).toEqual([]);
});
