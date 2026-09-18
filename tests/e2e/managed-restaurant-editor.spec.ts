import { expect, test } from '@playwright/test';

type Fixture = { profile: 'depositor' | 'claimant' | 'historical'; restaurantId: number; email: string };

const password = 'E2e-managed-editor-password-2026';
const photo = {
  name: 'nouvelle-photo-800px.png',
  mimeType: 'image/png',
  buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAyAAAAABCAYAAAAmaMpmAAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAAJcEhZcwAADsMAAA7DAcdvqGQAAAAaSURBVEhL7cExAQAAAMKg9U9tCy+gAAAATgYMgQABm0L0EAAAAABJRU5ErkJggg==', 'base64'),
};
const tooNarrowPhoto = {
  name: 'trop-petite.png',
  mimeType: 'image/png',
  buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL2WQAAAABJRU5ErkJggg==', 'base64'),
};
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
      await expect.poll(() => page.locator('[data-taxonomy-group]').evaluateAll(groups => groups.every(group => {
        const selected = group.querySelector('input[type="checkbox"]:checked');
        const required = group.querySelector<HTMLInputElement>('input[type="checkbox"]')?.required;
        return Boolean(selected) ? !required : required === true;
      }))).toBe(true);
      expect(errors).toEqual([]);
    });
  }

  test('the shared picker previews, accumulates and removes new photos before save', async ({ page }, testInfo) => {
    const depositor = fixtures.find(fixture => fixture.profile === 'depositor');
    expect(depositor).toBeTruthy();
    const key = testInfo.project.name.includes('mobile') ? 'mobile' : 'desktop';
    const fixture = mutationIds[key] ?? depositor!;

    await page.goto('/login');
    await page.locator('input[name="email"]').fill(fixture.email);
    await page.locator('input[name="password"]').fill(password);
    await page.getByRole('button', { name: 'Se connecter' }).click();
    await page.goto(`/account/restaurants/${fixture.restaurantId}/edit`);

    const picker = page.locator('[data-photo-picker]').filter({ has: page.locator('[data-owner-new-photos-input]') });
    const input = picker.locator('[data-owner-new-photos-input]');
    await input.setInputFiles({ ...photo, name: 'photo-un.png' });
    await expect(picker.locator('[data-new-photo-card]')).toHaveCount(1);
    await expect(picker.getByText('Nouvelle')).toBeVisible();
    await expect(picker.getByText('1 photo sélectionnée.')).toBeVisible();

    await input.setInputFiles([
      { ...photo, name: 'photo-deux.png' },
      { ...photo, name: 'photo-trois.png' },
    ]);
    await expect(picker.locator('[data-new-photo-card]')).toHaveCount(3);
    await expect(picker.getByText('3 photos sélectionnées.')).toBeVisible();
    await expect.poll(() => input.evaluate((element: HTMLInputElement) => [...element.files ?? []].map(file => file.name))).toEqual(['photo-un.png', 'photo-deux.png', 'photo-trois.png']);

    await input.setInputFiles(tooNarrowPhoto);
    await expect(picker.locator('[data-photo-picker-errors]')).toContainText('Cette image fait moins de 800 px de large.');
    await expect(picker.locator('[data-new-photo-card]')).toHaveCount(3);
    await picker.getByRole('button', { name: 'Retirer' }).first().click();
    await expect(picker.locator('[data-new-photo-card]')).toHaveCount(2);
    await expect(picker.getByText('2 photos sélectionnées.')).toBeVisible();
    await expect.poll(() => input.evaluate((element: HTMLInputElement) => [...element.files ?? []].map(file => file.name))).toEqual(['photo-deux.png', 'photo-trois.png']);
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
  });

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
    const persistedPhotos = page.locator('[data-owner-media-card]');
    await expect(persistedPhotos).toHaveCount(2);
    await expect(persistedPhotos.first().locator('[data-owner-media-up]')).toBeHidden();
    await expect(persistedPhotos.first().locator('[data-owner-media-down]')).toBeVisible();
    await expect(persistedPhotos.nth(1).locator('[data-owner-media-up]')).toBeVisible();
    await expect(persistedPhotos.nth(1).locator('[data-owner-media-down]')).toBeHidden();
    await persistedPhotos.first().getByRole('button', { name: 'Descendre' }).click();
    const pendingRemoval = persistedPhotos.nth(1);
    await pendingRemoval.getByRole('button', { name: 'Retirer' }).click();
    await expect(pendingRemoval).toHaveClass(/is-marked-for-removal/);
    await expect(pendingRemoval.getByText('Cette photo sera retirée à l’enregistrement')).toBeVisible();
    await expect(pendingRemoval.locator('[data-owner-media-remove-input]')).toBeEnabled();
    await pendingRemoval.getByRole('button', { name: 'Annuler' }).click();
    await expect(pendingRemoval).not.toHaveClass(/is-marked-for-removal/);
    await expect(pendingRemoval.locator('[data-owner-media-remove-input]')).toBeDisabled();
    await pendingRemoval.getByRole('button', { name: 'Retirer' }).click();
    const picker = page.locator('[data-photo-picker]').filter({ has: page.locator('[data-owner-new-photos-input]') });
    await picker.locator('[data-owner-new-photos-input]').setInputFiles([
      { ...photo, name: `persisted-one-${key}.png` },
      { ...photo, name: `persisted-two-${key}.png` },
    ]);
    await expect(picker.locator('[data-new-photo-card]')).toHaveCount(2);
    await page.getByRole('button', { name: 'Enregistrer les modifications' }).click();
    await expect(page.getByRole('status')).toContainText('Restaurant mis à jour.');

    await page.reload();
    await expect(page.locator('input[name="hours[0][slots][0][opens_at]"]')).toHaveValue('10:30');
    await expect(page.locator('[data-owner-media-card]')).toHaveCount(3);
    await expect(page.locator('[data-new-photo-card]')).toHaveCount(0);
    expect(errors).toEqual([]);
  });
});
