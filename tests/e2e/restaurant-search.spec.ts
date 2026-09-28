import { expect, test } from '@playwright/test';

test('two-field restaurant search works responsively', async ({ page }) => {
    await page.goto('/');
    const search = page.locator('[data-restaurant-search]').first();
    await expect(search.getByLabel('Localisation')).toHaveValue('Paris');
    await search.getByLabel('Localisation').focus();
    await expect(search.getByRole('button', { name: 'Autour de moi' })).toBeVisible();
    await page.locator('h1').click();
    await expect(search.getByRole('button', { name: 'Autour de moi' })).toBeHidden();
    await search.getByLabel('Localisation').focus();
    await search.locator('[data-city-name]').first().click();
    await search.getByLabel('Localisation').focus();
    await expect.poll(() => search.locator('[data-city-name]').count()).toBeGreaterThan(1);
    await search.getByLabel('Spécialité ou nom de restaurant').fill('burger');
    await expect(search.locator('[data-suggestions-list]')).toBeVisible();
    if ((page.viewportSize()?.width ?? 0) < 760) {
      expect(await search.evaluate(el => getComputedStyle(el).gridTemplateColumns.split(' ').length)).toBe(1);
      expect(await page.locator('body').evaluate(el => el.scrollWidth > el.clientWidth)).toBe(false);
    }
  });

test('official accent-insensitive commune selection keeps its INSEE identity and never falls back to Paris', async ({ page }) => {
  await page.goto('/');
  const search = page.locator('[data-restaurant-search]').first();
  const location = search.getByLabel('Localisation');
  await expect(location).toHaveValue('Paris');
  await location.fill('L Abergement Clemenciat');
  await expect(search.getByRole('option', { name: /Abergement-Clémenciat/ })).toBeVisible();
  await search.getByRole('option', { name: /Abergement-Clémenciat/ }).click();
  await expect(search.locator('[data-location-value]')).toHaveValue('01001');
  await search.getByRole('button', { name: 'Rechercher' }).click();
  await page.waitForURL(/\/restaurants\?city_code=01001/);
  await expect(page.getByText('Aucun restaurant halal référencé à L\'Abergement-Clémenciat pour le moment.')).toBeVisible();
  await expect(page.getByText('0 restaurant halal')).toBeVisible();
  expect(await page.locator('.restaurant-card').count()).toBe(0);
});

test('editing the default Paris text clears its city code and rejects an unknown location without navigation', async ({ page }) => {
  await page.goto('/');
  const search = page.locator('[data-restaurant-search]').first();
  const location = search.getByLabel('Localisation');
  await expect(search.locator('[data-location-value]')).toHaveValue('75056');
  await location.fill('Ville totalement inconnue');
  await expect(search.locator('[data-location-value]')).toHaveValue('');
  await search.getByRole('button', { name: 'Rechercher' }).click();
  await expect(search.getByText("Nous n'avons pas trouvé cette ville. Vérifiez l'orthographe ou sélectionnez une suggestion.")).toBeVisible();
  await expect(page).toHaveURL(/\/$/);
});

test('near me is requested only after the voluntary choice and keeps the search usable on refusal', async ({ page }) => {
  await page.goto('/');
  const search = page.locator('[data-restaurant-search]').first();
  await expect(search.getByLabel('Localisation')).toHaveValue('Paris');
  await search.getByLabel('Localisation').focus();
  await page.context().grantPermissions([]);
  await search.getByRole('button', { name: 'Autour de moi' }).click();
  await expect(search.getByText('Impossible d’obtenir votre position. Choisissez une ville.')).toBeVisible();
  await expect(search.getByLabel('Localisation')).toBeFocused();
});

test('directory starts without a city and exposes its mobile filter drawer', async ({ page }) => {
  await page.goto('/restaurants');
  const search = page.locator('[data-restaurant-search]');
  await expect(search.getByLabel('Localisation')).toHaveValue('');
  await expect(search.getByLabel('Localisation')).toHaveAttribute('placeholder', 'Ville ou localisation');
  await search.getByLabel('Localisation').focus();
  await expect(search.getByRole('button', { name: 'Autour de moi' })).toBeVisible();
  if ((page.viewportSize()?.width ?? 0) < 760) {
    await search.getByLabel('Localisation').press('Escape');
    const trigger = page.getByRole('button', { name: /Filtres/ });
    await trigger.click();
    await expect(page.getByRole('dialog', { name: 'Filtres' })).toBeVisible();
    await expect(page.getByRole('dialog').getByText('Spécialités', { exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Fermer les filtres' }).click();
    await expect(page.getByRole('dialog')).toBeHidden();
  }
});

test('directory filter options visibly collapse, expand, and keep a selected later option exposed', async ({ page }) => {
  const isMobile = (page.viewportSize()?.width ?? 0) < 760;
  await page.goto('/restaurants');

  if (isMobile) {
    await page.getByRole('button', { name: /Filtres/ }).click();
  }

  const drawer = page.locator('[data-filters-drawer]');
  const specialtyGroup = drawer.locator('[data-filter-group]').first();
  const specialtyOptions = specialtyGroup.locator('[data-filter-option]');
  const specialtyToggle = specialtyGroup.locator('[data-filter-toggle]');
  expect(await specialtyOptions.count()).toBeGreaterThan(8);
  await expect(specialtyOptions.nth(7)).toBeVisible();
  await expect(specialtyOptions.nth(8)).toBeHidden();
  await expect(specialtyToggle).toHaveText('Voir toutes les spécialités');

  const laterSpecialty = await specialtyOptions.nth(8).locator('input').getAttribute('value');
  await specialtyToggle.click();
  await expect(specialtyOptions.nth(8)).toBeVisible();
  await expect(specialtyToggle).toHaveText('Voir moins');
  await specialtyToggle.click();
  await expect(specialtyOptions.nth(8)).toBeHidden();

  const serviceGroup = drawer.locator('[data-filter-group]').nth(1);
  const serviceOptions = serviceGroup.locator('[data-filter-option]');
  const serviceToggle = serviceGroup.locator('[data-filter-toggle]');
  expect(await serviceOptions.count()).toBeGreaterThan(8);
  await expect(serviceOptions.nth(7)).toBeVisible();
  await expect(serviceOptions.nth(8)).toBeHidden();
  await expect(serviceToggle).toHaveText('Voir tous les services');
  await serviceToggle.click();
  await expect(serviceOptions.nth(8)).toBeVisible();
  await expect(serviceToggle).toHaveText('Voir moins');
  await serviceToggle.click();
  await expect(serviceOptions.nth(8)).toBeHidden();

  if (isMobile) {
    await page.getByRole('button', { name: 'Fermer les filtres' }).click();
  }
  await page.goto(`/restaurants?categories%5B%5D=${encodeURIComponent(laterSpecialty ?? '')}`);
  if (isMobile) {
    await page.getByRole('button', { name: /Filtres/ }).click();
  }
  await expect(drawer.locator('[data-filter-group]').first().locator('[data-filter-option]').nth(8)).toBeVisible();
  await expect(drawer.locator('[data-filter-group]').first().locator('[data-filter-toggle]')).toHaveText('Voir moins');
});

test('near me sends mocked coordinates only after the voluntary choice', async ({ page, context }) => {
  await context.grantPermissions(['geolocation']);
  await context.setGeolocation({ latitude: 48.8566, longitude: 2.3522 });
  await page.goto('/');
  const search = page.locator('[data-restaurant-search]').first();
  await search.getByLabel('Localisation').focus();
  await search.getByRole('button', { name: 'Autour de moi' }).click();
  await page.waitForURL(/\/restaurants\?.*lat=48\.8566.*lng=2\.3522/);
});

test('nearby state survives applying a directory filter', async ({ page, context }) => {
  await context.grantPermissions(['geolocation']);
  await context.setGeolocation({ latitude: 48.8566, longitude: 2.3522 });
  await page.goto('/');
  const search = page.locator('[data-restaurant-search]').first();
  await search.getByLabel('Localisation').focus();
  await search.getByRole('button', { name: 'Autour de moi' }).click();
  await page.waitForURL(/\/restaurants\?.*lat=48\.8566.*lng=2\.3522/);
  const filters = page.locator('[data-filters-drawer]');
  if ((page.viewportSize()?.width ?? 0) < 760) await page.getByRole('button', { name: /Filtres/ }).click();
  const specialty = filters.locator('input[name="categories[]"]').first();
  await specialty.check();
  if ((page.viewportSize()?.width ?? 0) < 760) await filters.getByRole('button', { name: 'Afficher les résultats' }).click();
  await page.waitForURL(/\/restaurants\?.*lat=48\.8566.*lng=2\.3522.*categories/);
  expect(new URL(page.url()).searchParams.has('city_code')).toBe(false);
});

test('city landing shares filters and keeps Paris when applying one', async ({ page }) => {
  await page.goto('/restos/paris');
  const search = page.locator('[data-restaurant-search]');
  await expect(search.getByLabel('Localisation')).toHaveValue('Paris');
  const filters = page.locator('[data-filters-drawer]');
  if ((page.viewportSize()?.width ?? 0) < 760) await page.getByRole('button', { name: /Filtres/ }).click();
  await expect(filters).toBeVisible();
  await filters.locator('input[name="categories[]"]').first().check();
  if ((page.viewportSize()?.width ?? 0) < 760) await filters.getByRole('button', { name: 'Afficher les résultats' }).click();
  await page.waitForURL(/\/restaurants\?.*city_code=75056.*categories/);
});

test('a specialty suggestion is exactly the Paris manual specialty filter without a competing text query', async ({ page }) => {
  await page.goto('/restaurants?city_code=75056&categories%5B%5D=pizzeria');
  const manualCount = (await page.locator('[data-results-count]').textContent())?.trim();
  expect(manualCount).toBeTruthy();

  await page.goto('/restos/paris');
  const search = page.locator('[data-restaurant-search]');
  const query = search.getByLabel('Spécialité ou nom de restaurant');
  await query.fill('pizz');
  await expect(search.locator('[data-suggestions-list]')).toBeVisible();
  await search.getByRole('option', { name: 'Pizzeria', exact: true }).click();
  await expect(query).toHaveValue('Pizzeria');
  await expect(query).not.toHaveAttribute('name', 'q');
  await search.getByRole('button', { name: 'Rechercher' }).click();
  await page.waitForURL(/\/restaurants\?.*city_code=75056.*categories/);

  const parameters = new URL(page.url()).searchParams;
  expect(parameters.has('q')).toBe(false);
  expect(parameters.getAll('categories[0]')).toContain('pizzeria');
  await expect(page.locator('[data-results-count]')).toHaveText(manualCount ?? '');
  await expect(page.locator('[data-directory-filter-form] input[name="categories[]"][value="pizzeria"]')).toBeChecked();
});

test('free text and an edit after a specialty suggestion submit text only', async ({ page }) => {
  await page.goto('/restos/paris');
  const search = page.locator('[data-restaurant-search]');
  const query = search.getByLabel('Spécialité ou nom de restaurant');
  await query.fill('pizz');
  await search.getByRole('option', { name: 'Pizzeria', exact: true }).click();
  await query.fill('Quick');
  await expect(query).toHaveAttribute('name', 'q');
  await search.getByRole('button', { name: 'Rechercher' }).click();
  await page.waitForURL(/\/restaurants\?.*q=Quick.*city_code=75056/);

  const parameters = new URL(page.url()).searchParams;
  expect(parameters.get('q')).toBe('Quick');
  expect(parameters.getAll('categories[0]')).not.toContain('pizzeria');
});

test('nearby results are limited to thirty kilometres and sorted by distance', async ({ page, context }) => {
  await context.grantPermissions(['geolocation']);
  await context.setGeolocation({ latitude: 48.8566, longitude: 2.3522 });
  await page.goto('/restaurants?near_me=1');
  await page.waitForURL(/\/restaurants\?.*lat=48\.85660.*lng=2\.35220/);
  await expect(page.locator('[data-results-count]')).not.toContainText('7 780');
  const distances = await page.locator('.restaurant-card .muted').allTextContents();
  const kilometres = distances.filter(text => text.startsWith('À ')).map(text => Number(text.replace(',', '.').match(/[\d.]+/)?.[0] ?? 999));
  expect(kilometres.length).toBeGreaterThan(0);
  expect(kilometres.every(value => value <= 30)).toBe(true);
  expect(kilometres.every((value, index) => index === 0 || kilometres[index - 1] <= value)).toBe(true);
});

test('the main search keeps filters selected in the mobile drawer', async ({ page, context }) => {
  test.skip((page.viewportSize()?.width ?? 0) >= 760, 'The desktop checkbox applies immediately.');
  await context.grantPermissions(['geolocation']);
  await context.setGeolocation({ latitude: 48.8566, longitude: 2.3522 });
  await page.goto('/restaurants?near_me=1');
  await page.waitForURL(/\/restaurants\?.*lat=48\.85660.*lng=2\.35220/);
  await page.getByRole('button', { name: /Filtres/ }).click();
  await page.locator('[data-filters-drawer] input[name="categories[]"]').first().check();
  await page.getByRole('button', { name: 'Fermer les filtres' }).click();
  await page.locator('[data-restaurant-search]').getByRole('button', { name: 'Rechercher' }).click();
  const parameters = new URL(page.url()).searchParams;
  expect(parameters.get('lat')).toBe('48.8566');
  expect(parameters.get('lng')).toBe('2.3522');
  expect(parameters.getAll('categories[0]')).not.toEqual([]);
});

test('near-me URL requests location and preserves compatible filters', async ({ page, context }) => {
  await context.grantPermissions(['geolocation']);
  await context.setGeolocation({ latitude: 48.8566, longitude: 2.3522 });
  await page.goto('/restaurants?near_me=1&q=burger&categories%5B%5D=burger');
  await page.waitForURL(/\/restaurants\?.*q=burger.*categories.*lat=48\.85660.*lng=2\.35220/);
  expect(new URL(page.url()).searchParams.has('near_me')).toBe(false);
});

test('near-me URL stays usable after a refused location request', async ({ page, context }) => {
  await context.grantPermissions([]);
  await page.goto('/restaurants?near_me=1&q=burger');
  await expect(page.getByText('Impossible d’obtenir votre position. Choisissez une ville.')).toBeVisible();
  expect(new URL(page.url()).searchParams.has('near_me')).toBe(false);
  expect(new URL(page.url()).searchParams.get('q')).toBe('burger');
});
