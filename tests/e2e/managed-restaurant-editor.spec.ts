import { expect, test } from '@playwright/test';

type Fixture = { profile: 'depositor' | 'claimant' | 'historical'; restaurantId: number; email: string };

const password = 'E2e-managed-editor-password-2026';
const rawFixtures = process.env.E2E_MANAGED_EDITOR_FIXTURES;
const fixtures: Fixture[] = rawFixtures ? JSON.parse(rawFixtures) : [];
const mutationIds = process.env.E2E_MANAGED_EDITOR_MUTATION_IDS ? JSON.parse(process.env.E2E_MANAGED_EDITOR_MUTATION_IDS) as Record<string, { restaurantId: number; email: string }> : {};

test.describe('Éditeur de fiche gérée', () => {
  test.skip(!rawFixtures, 'E2E_MANAGED_EDITOR_FIXTURES is required; the preproduction runner creates isolated non-human fixtures.');

  for (const fixture of fixtures) {
    test(`${fixture.profile} can open the shared editor`, async ({ page }) => {
      const errors: string[] = [];
      page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
      page.on('requestfailed', request => errors.push(`${request.method()} ${request.url()}`));

      await page.goto('/login');
      await page.locator('input[name="email"]').fill(fixture.email);
      await page.locator('input[name="password"]').fill(password);
      await page.getByRole('button', { name: 'Se connecter' }).click();
      await page.goto(`/account/restaurants/${fixture.restaurantId}/edit`);

      await expect(page.getByRole('heading', { name: /Modifier/ })).toBeVisible();
      await expect(page.getByText('Spécialités et services')).toBeVisible();
      await expect(page.getByRole('group', { name: 'Horaires' })).toBeVisible();
      await expect(page.getByRole('group', { name: 'Photos' })).toBeVisible();
      await expect(page.locator('input[name="contact_email"]')).toHaveCount(0);
      expect(errors).toEqual([]);
    });
  }

  test('a depositor persists changed hours and media on the shared editor', async ({ page }, testInfo) => {
    const depositor = fixtures.find(fixture => fixture.profile === 'depositor');
    expect(depositor).toBeTruthy();
    const key = testInfo.project.name.includes('mobile') ? 'mobile' : 'desktop';
    const mutationFixture = mutationIds[key] ?? depositor!;
    const errors: string[] = [];
    page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
    page.on('requestfailed', request => errors.push(`${request.method()} ${request.url()}`));

    await page.goto('/login');
    await page.locator('input[name="email"]').fill(mutationFixture.email);
    await page.locator('input[name="password"]').fill(password);
    await page.getByRole('button', { name: 'Se connecter' }).click();
    await page.goto(`/account/restaurants/${mutationFixture.restaurantId}/edit`);

    await page.locator('select[name="hours[0][status]"]').selectOption('slots');
    await page.locator('input[name="hours[0][slots][0][opens_at]"]').fill('10:30');
    await page.locator('input[name="hours[0][slots][0][closes_at]"]').fill('14:30');
    await expect(page.locator('[data-owner-media-card]')).toHaveCount(2);
    await page.locator('[data-owner-media-card]').first().getByRole('button', { name: 'Descendre' }).click();
    await page.locator('[data-owner-media-card]').nth(1).getByLabel(/Retirer cette photo/).check();
    await page.getByRole('button', { name: 'Enregistrer les modifications' }).click();
    await expect(page.getByRole('status')).toContainText('Restaurant mis à jour.');

    await page.reload();
    await expect(page.locator('input[name="hours[0][slots][0][opens_at]"]')).toHaveValue('10:30');
    await expect(page.locator('[data-owner-media-card]')).toHaveCount(1);
    expect(errors).toEqual([]);
  });
});
