import { expect, test } from '@playwright/test';

const email = process.env.PREPROD_ADMIN_EMAIL;
const password = process.env.PREPROD_ADMIN_PASSWORD;

test('homepage keeps its ordered SSR content and search', async ({ page }) => {
  const errors: string[] = []; const failed: string[] = [];
  page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
  page.on('requestfailed', request => failed.push(request.url()));
  await page.goto('/');
  await expect(page.getByRole('heading', { name: 'Trouvez votre restaurant halal, simplement.' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Trouvez facilement un restaurant halal' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Le halal au quotidien, et bien plus encore' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Des informations pour mieux choisir' })).toBeVisible();
  await expect(page.getByRole('link', { name: 'Ajouter un restaurant' }).last()).toHaveAttribute('href', /\/ajouter-un-restaurant$/);
  expect(await page.locator('body').evaluate(body => body.scrollWidth <= window.innerWidth)).toBeTruthy();
  expect(errors).toEqual([]); expect(failed).toEqual([]);
});

test.skip(!email || !password, 'PREPROD_ADMIN_EMAIL and PREPROD_ADMIN_PASSWORD are required.');
test('administrator can open the dedicated homepage settings screen', async ({ page }) => {
  await page.goto('/admin');
  await page.locator('input[type="email"]').fill(email!);
  await page.locator('input[type="password"]').fill(password!);
  await page.locator('button[type="submit"]').click();
  await expect(page).toHaveURL(/\/admin$/);
  await page.goto('/admin/homepage');
  await expect(page.getByRole('heading', { name: "Page d'accueil" })).toBeVisible();
  await expect(page.getByText('Contenu restaurants halal', { exact: true })).toBeVisible();
  await expect(page.getByText('CTA Ajouter un restaurant', { exact: true })).toBeVisible();
});
