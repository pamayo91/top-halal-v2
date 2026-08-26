import { expect, test } from '@playwright/test';

for (const device of ['desktop', 'mobile']) {
  test(`public directory journey is clean on ${device}`, async ({ page }) => {
    const errors: string[] = []; const failed: string[] = []; const legacy: string[] = [];
    page.on('console', m => { if (m.type() === 'error') errors.push(m.text()); });
    page.on('requestfailed', r => failed.push(r.url()));
    page.on('request', r => { if (/wp-conten(?:t|u)|wordpress/i.test(r.url())) legacy.push(r.url()); });
    await page.goto('/');
    await expect(page.getByRole('heading', { name: 'Trouvez votre restaurant halal, simplement.' })).toBeVisible();
    await page.getByRole('link', { name: 'Restaurants' }).first().click();
    await expect(page.getByRole('heading', { name: 'Restaurants halal' })).toBeVisible();
    await page.locator('#q').fill('zzzz-introuvable');
    await page.getByRole('button', { name: 'Appliquer les filtres' }).click();
    await expect(page.getByText('Aucun restaurant ne correspond.')).toBeVisible();
    await page.getByRole('link', { name: 'Voir toutes les adresses' }).click();
    const card = page.locator('.restaurant-card a').first();
    await expect(card).toBeVisible();
    await card.click();
    await expect(page.locator('h1')).toBeVisible();
    await expect(page.locator('nav[aria-label="Fil d’Ariane"]')).toBeVisible();
    await page.goto('/blog');
    await expect(page.getByRole('heading', { name: 'Le guide Top Halal' })).toBeVisible();
    expect(errors).toEqual([]); expect(failed).toEqual([]); expect(legacy).toEqual([]);
  });
}
