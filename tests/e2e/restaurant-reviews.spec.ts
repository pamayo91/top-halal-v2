import { expect, test } from '@playwright/test';

test('public restaurant review uses accessible stars, requires identity verification and rejects URLs', async ({ page }) => {
  const errors: string[] = []; page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
  await page.goto('/resto/hayat-2'); await expect(page.locator('h1')).toBeVisible(); await expect(page.locator('[data-review-aggregate]')).toContainText('avis'); await page.getByText('Donner mon avis', { exact: true }).click();
  await expect(page.getByText('Prénom ou pseudo', { exact: true })).toBeVisible(); await expect(page.getByText('Choisissez une note', { exact: true })).toBeVisible(); await expect(page.locator('select[name="rating"]')).toHaveCount(0); await expect(page.locator('input[name="title"]')).toHaveCount(0);
  await page.locator('input[name="name"]').fill('Codex review'); await page.locator('input[name="email"]').fill('codex-review-test@example.invalid'); await page.getByLabel('5 étoiles').check(); await expect(page.getByText('Excellent', { exact: true })).toBeVisible(); await page.locator('textarea[name="content"]').fill('Validation sans lien.'); await page.getByRole('button',{name:'Envoyer mon avis'}).click(); await expect(page.getByRole('status')).toContainText('Vérifiez votre adresse e-mail');
  await page.getByText('Donner mon avis', { exact: true }).click(); await page.locator('input[name="name"]').fill('Codex review'); await page.locator('input[name="email"]').fill('codex-review-test@example.invalid'); await page.getByLabel('5 étoiles').check(); await page.locator('textarea[name="content"]').fill('https://example.invalid'); await page.getByRole('button',{name:'Envoyer mon avis'}).click(); await expect(page.getByRole('alert')).toContainText('liens et URLs'); expect(errors).toEqual([]);
});

test('restaurant review form is a single column on mobile', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/resto/hayat-2'); await page.getByText('Donner mon avis', { exact: true }).click();
  await expect(page.locator('.review-identity-fields')).toHaveCSS('grid-template-columns', '358px');
  await page.getByLabel('1 étoile').focus(); await page.keyboard.press('ArrowRight'); await expect(page.getByLabel('2 étoiles')).toBeChecked();
});
