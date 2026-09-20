import { expect, test } from '@playwright/test';

test('public restaurant review uses accessible stars, requires identity verification and rejects URLs', async ({ page }) => {
  const errors: string[] = []; page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
  await page.goto('/resto/hayat-2'); await expect(page.locator('h1')).toBeVisible(); await expect(page.locator('[data-review-aggregate]')).toContainText('avis'); await page.getByText('Donner mon avis', { exact: true }).click();
  await expect(page.getByText('Prénom ou pseudo', { exact: true })).toBeVisible(); await expect(page.getByText('Choisissez une note', { exact: true })).toBeVisible(); await expect(page.locator('select[name="rating"]')).toHaveCount(0); await expect(page.locator('input[name="title"]')).toHaveCount(0);
  const reviewForm = page.locator('form.review-form:not(.report-form)');
  for (const [rating, label] of [['1', 'Décevant'], ['2', 'Moyen'], ['3', 'Bien'], ['4', 'Très bien'], ['5', 'Excellent']]) { await reviewForm.locator(`label[for="review-rating-${rating}"]`).click(); await expect(reviewForm.locator(`input[name="rating"][value="${rating}"]`)).toBeChecked(); await expect(reviewForm.getByText(label, { exact: true })).toBeVisible(); }
  await reviewForm.locator('label[for="review-rating-2"]').click(); await reviewForm.locator('label[for="review-rating-4"]').hover(); await expect(reviewForm.getByText('Très bien', { exact: true })).toBeVisible(); await page.locator('h1').hover(); await expect(reviewForm.getByText('Moyen', { exact: true })).toBeVisible();
  await reviewForm.locator('input[name="name"]').fill('Codex review'); await reviewForm.locator('input[name="email"]').fill('codex-review-test@example.invalid'); await reviewForm.locator('textarea[name="content"]').fill('Validation sans lien.'); await reviewForm.getByRole('button',{name:'Envoyer mon avis'}).click(); await expect(page).toHaveURL(/\/resto\/hayat-2#avis$/); await expect(page.locator('#avis')).toBeInViewport(); await expect(page.getByRole('status')).toContainText('Vérifiez votre adresse e-mail');
  await page.getByText('Donner mon avis', { exact: true }).click(); await reviewForm.locator('input[name="name"]').fill('Codex review'); await reviewForm.locator('input[name="email"]').fill('codex-review-test@example.invalid'); await reviewForm.locator('label[for="review-rating-5"]').click(); await reviewForm.locator('textarea[name="content"]').fill('https://example.invalid'); await reviewForm.getByRole('button',{name:'Envoyer mon avis'}).click(); await expect(reviewForm.getByRole('alert')).toContainText('liens et URLs'); expect(errors).toEqual([]);
});

test('restaurant review form is a single column on mobile', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/resto/hayat-2'); await page.getByText('Donner mon avis', { exact: true }).click();
  expect(await page.locator('.review-identity-fields').evaluate(element => getComputedStyle(element).gridTemplateColumns.split(' ').length)).toBe(1);
  await page.locator('label[for="review-rating-1"]').click(); await page.getByLabel('1 étoile').focus(); await page.keyboard.press('ArrowRight'); await expect(page.getByLabel('2 étoiles')).toBeChecked();
});

test('restaurant correction form shares the compact review card on desktop and mobile', async ({ page }) => {
  const errors: string[] = []; page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
  await page.goto('/resto/hayat-2');
  await expect(page.getByText('Une information à corriger ?', { exact: true })).toBeVisible();
  expect(await page.evaluate(() => {
    const information = document.querySelector('.restaurant-information')!;
    const reviews = document.querySelector('#avis')!;
    const report = document.querySelector('.report-correction')!;
    const bottom = (element: Element) => element.getBoundingClientRect().bottom + scrollY;
    const titleTop = (section: Element) => section.querySelector('h2')!.getBoundingClientRect().top + scrollY;
    return Math.round(titleTop(reviews) - bottom(information)) === Math.round(titleTop(report) - bottom(reviews));
  })).toBe(true);
  await page.getByText('Signaler une erreur', { exact: true }).click();
  const correctionForm = page.locator('.report-form'); const reviewForm = page.locator('form.review-form:not(.report-form)');
  await expect(correctionForm).toBeVisible();
  expect(await correctionForm.evaluate(element => getComputedStyle(element).maxWidth)).toBe(await reviewForm.evaluate(element => getComputedStyle(element).maxWidth));
  expect((await correctionForm.boundingBox())!.width).toBeLessThanOrEqual((await page.locator('.restaurant-main').boundingBox())!.width);
  await expect(correctionForm.locator('textarea[name="message"]')).toHaveCSS('min-height', '128px');
  await expect(correctionForm.locator('.review-form-actions')).toHaveCSS('justify-content', 'flex-end');
  await correctionForm.locator('input[name="email"]').fill('invalid'); await correctionForm.getByRole('button', { name: 'Envoyer le signalement' }).click();
  await expect(correctionForm.locator('input[name="email"]')).toBeFocused(); expect(errors).toEqual([]);
  await page.setViewportSize({ width: 390, height: 844 }); await page.reload(); await page.getByText('Signaler une erreur', { exact: true }).click();
  const mobileForm = page.locator('.report-form'); const mobileAction = mobileForm.locator('.review-form-actions');
  expect((await mobileForm.boundingBox())!.width).toBeLessThanOrEqual(358);
  expect((await mobileAction.locator('.button').boundingBox())!.width).toBeLessThanOrEqual((await mobileAction.boundingBox())!.width);
});
