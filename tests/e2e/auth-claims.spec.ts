import { expect, test } from '@playwright/test';

const projectKey = (name: string) => name.includes('mobile') ? 'mobile' : 'desktop';
const password = 'E2e-test-password-2026';
const restaurantId = process.env.E2E_RESTAURANT_ID;
const rejectRestaurantId = process.env.E2E_REJECT_RESTAURANT_ID;
const runId = process.env.E2E_RUN_ID;

test.setTimeout(90_000);

test('authentication, mandatory password change, claims and permissions', async ({ page }, testInfo) => {
  expect(restaurantId).toBeTruthy();
  expect(rejectRestaurantId).toBeTruthy();
  expect(runId).toBeTruthy();
  const key = projectKey(testInfo.project.name);
  const prefix = `e2e-${runId}-${key}`;
  const consoleErrors: string[] = [];
  const networkErrors: string[] = [];
  page.on('console', message => {
    if (message.type() === 'error' && !message.text().includes('status of 403')) consoleErrors.push(message.text());
  });
  page.on('requestfailed', request => networkErrors.push(`${request.method()} ${request.url()}`));

  await page.goto('/register');
  await page.locator('input[name="name"]').fill('Inscription E2E');
  await page.locator('input[name="email"]').fill(`${prefix}-registered@example.invalid`);
  await page.locator('input[name="password"]').fill(password);
  await page.locator('input[name="password_confirmation"]').fill(password);
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await expect(page.getByRole('heading', { name: 'Mon compte' })).toBeVisible();
  await page.getByRole('button', { name: 'Déconnexion' }).click();

  await page.goto('/login');
  await page.locator('input[name="email"]').fill(`${prefix}-registered@example.invalid`);
  await page.locator('input[name="password"]').fill('wrong-password');
  await page.getByRole('button', { name: 'Se connecter' }).click();
  await expect(page.getByRole('alert')).toContainText('identifiants');
  await page.locator('input[name="password"]').fill(password);
  await page.getByRole('button', { name: 'Se connecter' }).click();
  await expect(page.getByRole('heading', { name: 'Mon compte' })).toBeVisible();
  await page.getByRole('button', { name: 'Déconnexion' }).click();

  await page.goto('/login');
  await page.locator('input[name="email"]').fill(`${prefix}-legacy@example.invalid`);
  await page.locator('input[name="password"]').fill(password);
  await page.getByRole('button', { name: 'Se connecter' }).click();
  await expect(page.getByRole('heading', { name: 'Choisissez votre mot de passe' })).toBeVisible();
  await page.goto('/account');
  await expect(page.getByRole('heading', { name: 'Choisissez votre mot de passe' })).toBeVisible();
  await page.locator('input[name="current_password"]').fill(password);
  await page.locator('input[name="password"]').fill('E2e-updated-password-2026');
  await page.locator('input[name="password_confirmation"]').fill('E2e-updated-password-2026');
  await page.getByRole('button', { name: 'Mettre à jour' }).click();
  await expect(page.getByRole('heading', { name: 'Mon compte' })).toBeVisible();
  await page.getByRole('button', { name: 'Déconnexion' }).click();
  await page.goto('/login');
  await page.locator('input[name="email"]').fill(`${prefix}-legacy@example.invalid`);
  await page.locator('input[name="password"]').fill(password);
  await page.getByRole('button', { name: 'Se connecter' }).click();
  await expect(page.getByRole('alert')).toContainText('identifiants');

  await page.goto('/forgot-password');
  await page.locator('input[name="email"]').fill(`${prefix}-registered@example.invalid`);
  await page.getByRole('button', { name: 'Envoyer le lien' }).click();
  await expect(page.getByRole('status')).toContainText('Si ce compte existe');

  await page.goto('/login');
  await page.locator('input[name="email"]').fill(`${prefix}-owner@example.invalid`);
  await page.locator('input[name="password"]').fill(password);
  await page.getByRole('button', { name: 'Se connecter' }).click();
  await page.goto(`/restaurants/${restaurantId}/claim`);
  await page.locator('textarea[name="message"]').fill(`Demande E2E ${key} de validation.`);
  await page.getByRole('button', { name: 'Envoyer la demande' }).click();
  await expect(page.locator('[data-claim-status]')).toContainText('pending');
  const ownerDenied = await page.evaluate((url) => fetch(url).then(response => response.status), `/account/restaurants/${restaurantId}/edit`);
  expect(ownerDenied).toBe(403);
  await page.getByRole('button', { name: 'Déconnexion' }).click();

  await page.goto('/login');
  await page.locator('input[name="email"]').fill(`${prefix}-admin@example.invalid`);
  await page.locator('input[name="password"]').fill(password);
  await page.getByRole('button', { name: 'Se connecter' }).click();
  await page.goto('/bo/claims');
  await expect(page.getByRole('heading', { name: 'Demandes en attente' })).toBeVisible();
  await page.locator('article').filter({ hasText: `Demande E2E ${key} de validation.` }).getByRole('button', { name: 'Approuver' }).click();
  await expect(page.getByRole('status')).toContainText('approuvée');
  await page.getByRole('button', { name: 'Déconnexion' }).click();

  await page.goto('/login');
  await page.locator('input[name="email"]').fill(`${prefix}-owner@example.invalid`);
  await page.locator('input[name="password"]').fill(password);
  await page.getByRole('button', { name: 'Se connecter' }).click();
  await page.goto(`/account/restaurants/${restaurantId}/edit`);
  await expect(page.getByRole('heading', { name: /Modifier/ })).toBeVisible();
  await page.getByRole('button', { name: 'Déconnexion' }).click();

  await page.goto('/login');
  await page.locator('input[name="email"]').fill(`${prefix}-third@example.invalid`);
  await page.locator('input[name="password"]').fill(password);
  await page.getByRole('button', { name: 'Se connecter' }).click();
  await page.goto(`/restaurants/${rejectRestaurantId}/claim`);
  await page.locator('textarea[name="message"]').fill(`Demande E2E ${key} à refuser.`);
  await page.getByRole('button', { name: 'Envoyer la demande' }).click();
  const rejectedClaimUrl = page.url();
  await page.getByRole('button', { name: 'Déconnexion' }).click();

  await page.goto('/login');
  await page.locator('input[name="email"]').fill(`${prefix}-admin@example.invalid`);
  await page.locator('input[name="password"]').fill(password);
  await page.getByRole('button', { name: 'Se connecter' }).click();
  await page.goto('/bo/claims');
  const rejection = page.locator('article').filter({ hasText: `Demande E2E ${key} à refuser.` });
  await rejection.locator('input[name="admin_note"]').fill('Justificatif absent.');
  await rejection.getByRole('button', { name: 'Refuser' }).click();
  await expect(page.getByRole('status')).toContainText('refusée');
  await page.getByRole('button', { name: 'Déconnexion' }).click();

  await page.goto('/login');
  await page.locator('input[name="email"]').fill(`${prefix}-third@example.invalid`);
  await page.locator('input[name="password"]').fill(password);
  await page.getByRole('button', { name: 'Se connecter' }).click();
  await page.goto(rejectedClaimUrl);
  await expect(page.locator('[data-claim-status]')).toContainText('rejected');
  const denied = await page.evaluate((url) => fetch(url).then(response => response.status), `/account/restaurants/${restaurantId}/edit`);
  expect(denied).toBe(403);

  expect(consoleErrors).toEqual([]);
  expect(networkErrors).toEqual([]);
});
