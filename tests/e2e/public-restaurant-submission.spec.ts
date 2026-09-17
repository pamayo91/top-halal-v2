import { expect, test } from '@playwright/test';

const cover = {
  name: 'couverture-800px.png',
  mimeType: 'image/png',
  buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAyAAAAABCAYAAAAmaMpmAAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAAJcEhZcwAADsMAAA7DAcdvqGQAAAAaSURBVEhL7cExAQAAAMKg9U9tCy+gAAAATgYMgQABm0L0EAAAAABJRU5ErkJggg==', 'base64'),
};

async function fillRestaurantAndAddress(page: import('@playwright/test').Page, suffix: string) {
  await page.goto('/ajouter-un-restaurant');
  await page.locator('[data-restaurant-name]').fill(`Restaurant validation ${suffix}`);
  await page.getByLabel('Viande halal').check();
  await page.getByRole('button', { name: 'Continuer' }).click();
  await expect(page.getByRole('heading', { name: 'L’adresse' })).toBeVisible();
  await page.getByLabel('Adresse du restaurant').fill('46 Boulevard du Temple Paris');
  await expect(page.locator('[data-address-results] button').first()).toBeVisible();
  await page.locator('[data-address-results] button').first().click();
  await expect(page.locator('[data-address-selected]')).toBeVisible();
  await page.getByRole('button', { name: 'Continuer' }).click();
  await expect(page.getByRole('heading', { name: 'Les informations utiles' })).toBeVisible();
  await page.locator('[name="categories[]"]').last().check();
  await page.locator('[name="features[]"]').last().check();
  await page.getByRole('button', { name: 'Continuer' }).click();
  await expect(page.getByRole('heading', { name: 'Les photos' })).toBeVisible();
}

async function expectCopyControlWithoutOverlap(page: import('@playwright/test').Page) {
  await expect.poll(() => page.locator('[data-copy-hours]').evaluate(button => {
    const buttonRect = button.getBoundingClientRect();
    return [...document.querySelectorAll<HTMLElement>('.hours-copy fieldset label')].every(label => {
      const labelRect = label.getBoundingClientRect();
      return buttonRect.right <= labelRect.left || buttonRect.left >= labelRect.right
        || buttonRect.bottom <= labelRect.top || buttonRect.top >= labelRect.bottom;
    });
  })).toBe(true);
}

test('public restaurant contribution blocks an empty halal choice and identifies a duplicate', async ({ page }) => {
  await page.goto('/ajouter-un-restaurant');
  expect(await page.locator('[data-restaurant-name]').evaluate(input => input.nextElementSibling?.matches('[data-name-duplicates]'))).toBe(true);
  await page.locator('[data-restaurant-name]').fill('O Sha');
  await page.getByRole('button', { name: 'Continuer' }).click();
  await expect(page.locator('[data-halal-error]')).toBeVisible();

  await page.getByLabel('Viande halal').check();
  await expect(page.locator('[data-name-duplicates]')).toContainText('O Sha');
  await expect(page.locator('[data-name-duplicates]').getByRole('link', { name: /O Sha/ })).toBeVisible();
});

test('public restaurant contribution requires a suggested address and never exposes technical details', async ({ page }) => {
  await page.goto('/ajouter-un-restaurant');
  await page.locator('[data-restaurant-name]').fill('Adresse obligatoire');
  await page.getByLabel('Viande halal').check();
  await page.getByRole('button', { name: 'Continuer' }).click();
  await expect(page.getByText("Commencez à saisir l'adresse du restaurant, puis sélectionnez la bonne adresse dans la liste proposée.")).toBeVisible();
  await expect(page.getByText('Votre adresse exacte n’apparaît pas ? Sélectionnez l’adresse la plus proche proposée, puis ajustez précisément la position du restaurant sur la carte.')).toBeVisible();
  await expect(page.getByLabel('Code INSEE')).toHaveCount(0);
  await page.getByRole('button', { name: 'Continuer' }).click();
  await expect(page.getByRole('heading', { name: 'L’adresse' })).toBeVisible();
});

test('public restaurant contribution restores one correctly sized map after returning from step 3', async ({ page }, testInfo) => {
  await page.goto('/ajouter-un-restaurant');
  await page.locator('[data-restaurant-name]').fill(`Carte retour ${testInfo.project.name}-${crypto.randomUUID()}`);
  await page.getByLabel('Viande halal').check();
  await page.getByRole('button', { name: 'Continuer' }).click();
  await page.getByLabel('Adresse du restaurant').fill('46 Boulevard du Temple Paris');
  await expect(page.locator('[data-address-results] button').first()).toBeVisible();
  await page.locator('[data-address-results] button').first().click();

  const map = page.locator('[data-address-map]');
  await expect(map.locator('.leaflet-marker-icon')).toHaveCount(1);
  const position = await Promise.all([page.locator('[data-latitude]').inputValue(), page.locator('[data-longitude]').inputValue()]);
  await page.getByRole('button', { name: 'Continuer' }).click();
  await expect(page.getByRole('heading', { name: 'Les informations utiles' })).toBeVisible();
  await page.getByRole('button', { name: 'Retour' }).click();
  await expect(page.getByRole('heading', { name: 'L’adresse' })).toBeVisible();
  await expect(map.locator('.leaflet-marker-icon')).toHaveCount(1);
  await expect.poll(async () => map.evaluate(element => {
    const mapBox = element.getBoundingClientRect();
    const markerBox = element.querySelector('.leaflet-marker-icon')?.getBoundingClientRect();
    if (!markerBox) return false;
    const markerX = markerBox.left + markerBox.width / 2;
    const markerY = markerBox.top + markerBox.height / 2;
    return element.clientWidth > 0 && element.clientHeight > 0
      && markerX > mapBox.left && markerX < mapBox.right
      && markerY > mapBox.top && markerY < mapBox.bottom;
  })).toBe(true);
  expect(await Promise.all([page.locator('[data-latitude]').inputValue(), page.locator('[data-longitude]').inputValue()])).toEqual(position);
});

test('public restaurant contribution keeps step-three contact fields in the requested desktop rows', async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== 'desktop-chromium', 'This layout is specific to the two-column desktop form.');
  await page.goto('/ajouter-un-restaurant');
  await page.locator('[data-restaurant-name]').fill(`Contacts ${crypto.randomUUID()}`);
  await page.getByLabel('Viande halal').check();
  await page.getByRole('button', { name: 'Continuer' }).click();
  await page.getByLabel('Adresse du restaurant').fill('46 Boulevard du Temple Paris');
  await expect(page.locator('[data-address-results] button').first()).toBeVisible();
  await page.locator('[data-address-results] button').first().click();
  await page.getByRole('button', { name: 'Continuer' }).click();

  await page.locator('.submission-contact-grid').scrollIntoViewIfNeeded();
  const positions = await page.locator('.submission-contact-grid').evaluate(grid => {
    const box = (selector: string) => (grid.querySelector(selector) as HTMLElement).getBoundingClientRect();
    const phone = box('.submission-phone-field');
    const website = box('label[for="restaurant-website"]');
    const instagram = box('label[for="restaurant-instagram"]');
    const facebook = box('label[for="restaurant-facebook"]');
    const tiktok = box('label[for="restaurant-tiktok"]');

    return {
      gridWidth: grid.getBoundingClientRect().width,
      phoneWidth: phone.width,
      websiteWidth: website.width,
      phoneBeforeWebsite: phone.bottom <= website.top,
      websiteInstagramRow: Math.abs(website.top - instagram.top) < 1,
      facebookTiktokRow: Math.abs(facebook.top - tiktok.top) < 1,
      socialRowsOrdered: website.bottom <= facebook.top,
    };
  });

  expect(positions.phoneWidth).toBeCloseTo(positions.websiteWidth, 1);
  expect(positions.phoneBeforeWebsite).toBe(true);
  expect(positions.websiteInstagramRow).toBe(true);
  expect(positions.facebookTiktokRow).toBe(true);
  expect(positions.socialRowsOrdered).toBe(true);
});

test('public restaurant contribution keeps compact mixed hours and copied slots across steps', async ({ page }, testInfo) => {
  const errors: string[] = [];
  page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
  page.on('requestfailed', request => errors.push(`${request.method()} ${request.url()}`));
  await page.goto('/ajouter-un-restaurant');
  await page.locator('[data-restaurant-name]').fill('Horaires compacts');
  await page.getByLabel('Viande halal').check();
  await page.getByRole('button', { name: 'Continuer' }).click();
  await page.getByLabel('Adresse du restaurant').fill('46 Boulevard du Temple Paris');
  await expect(page.locator('[data-address-results] button').first()).toBeVisible();
  await page.locator('[data-address-results] button').first().click();
  await page.getByRole('button', { name: 'Continuer' }).click();

  const monday = page.locator('[data-hours-day="monday"]');
  const tuesday = page.locator('[data-hours-day="tuesday"]');
  const sunday = page.locator('[data-hours-day="sunday"]');
  await page.locator('[name="categories[]"]').first().check();
  await page.locator('[name="features[]"]').first().check();
  await page.getByLabel('État Lundi').selectOption('slots');
  await expect(page.getByLabel('État Lundi')).toHaveJSProperty('offsetWidth', 132);
  await expect(page.getByLabel('État Lundi')).toHaveJSProperty('offsetHeight', 34);
  await expect(monday.locator('[data-hours-second-slot]')).toBeHidden();
  await monday.locator('[name="hours[monday][first_open]"]').fill('00:00');
  await monday.locator('[name="hours[monday][first_close]"]').fill('09:30');
  await monday.getByRole('button', { name: '+ 2ème plage' }).click();
  await monday.locator('[name="hours[monday][second_open]"]').fill('18:45');
  await monday.locator('[name="hours[monday][second_close]"]').fill('23:59');
  await expect(monday.locator('[name="hours[monday][first_open]"]')).toHaveValue('00:00');
  await expect(monday.locator('[name="hours[monday][first_close]"]')).toHaveValue('09:30');
  await expect(monday.locator('[name="hours[monday][second_open]"]')).toHaveValue('18:45');
  await expect(monday.locator('[name="hours[monday][second_close]"]')).toHaveValue('23:59');
  await expect(monday.locator('[name="hours[monday][first_open]"]')).toHaveJSProperty('offsetWidth', 104);
  if (testInfo.project.name === 'desktop-chromium') {
    await expect.poll(async () => monday.evaluate(day => {
      const secondRange = day.querySelector('[data-hours-second-slot]')?.getBoundingClientRect();
      const remove = day.querySelector('[data-remove-hours-slot]')?.getBoundingClientRect();
      return Boolean(secondRange && remove && remove.left >= secondRange.right && Math.abs(remove.top - secondRange.top) < secondRange.height);
    })).toBe(true);
  }
  await monday.getByRole('button', { name: 'Supprimer la 2ème plage' }).click();
  await expect(monday.locator('[data-hours-second-slot]')).toBeHidden();
  await monday.getByRole('button', { name: '+ 2ème plage' }).click();
  await monday.locator('[name="hours[monday][second_open]"]').fill('18:45');
  await monday.locator('[name="hours[monday][second_close]"]').fill('23:59');
  await page.getByLabel('État Mardi').selectOption('all_day');
  await expect(tuesday.locator('[data-hours-slots]')).toBeHidden();
  await expect(sunday.locator('[data-hours-slots]')).toBeHidden();

  await page.locator('[data-copy-source]').selectOption('monday');
  await page.locator('[data-copy-target="tuesday"]').check();
  await page.locator('[data-copy-target="wednesday"]').check();
  await page.getByRole('button', { name: 'Recopier', exact: true }).click();
  await expect(page.getByLabel('État Mardi')).toHaveValue('slots');
  await expect(tuesday.locator('[name="hours[tuesday][second_close]"]')).toHaveValue('23:59');
  await page.locator('[data-hours-day="wednesday"] [name="hours[wednesday][first_open]"]').fill('10:10');
  await page.locator('[data-hours-day="wednesday"] [name="hours[wednesday][first_close]"]').fill('12:00');
  await expect(page.locator('[data-hours-day="wednesday"] [name="hours[wednesday][first_close]"]')).toHaveValue('12:00');
  await expectCopyControlWithoutOverlap(page);

  await page.getByRole('button', { name: 'Continuer' }).click();
  await expect(page.getByRole('heading', { name: 'Les photos' })).toBeVisible();
  await page.getByRole('button', { name: 'Retour' }).click();
  await expect(page.getByRole('heading', { name: 'Les informations utiles' })).toBeVisible();
  await expect(monday.locator('[name="hours[monday][first_open]"]')).toHaveValue('00:00');
  await expect(monday.locator('[name="hours[monday][second_close]"]')).toHaveValue('23:59');
  await expect(tuesday.locator('[name="hours[tuesday][second_open]"]')).toHaveValue('18:45');
  await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
  expect(errors).toEqual([]);
});

test('public restaurant contribution keeps the hours editor within standard desktop and tablet viewports', async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== 'desktop-chromium', 'The desktop project supplies the standard desktop and tablet viewport checks.');
  await page.setViewportSize({ width: 1024, height: 900 });
  await page.goto('/ajouter-un-restaurant');
  await page.locator('[data-restaurant-name]').fill('Horaires tablette');
  await page.getByLabel('Viande halal').check();
  await page.getByRole('button', { name: 'Continuer' }).click();
  await page.getByLabel('Adresse du restaurant').fill('46 Boulevard du Temple Paris');
  await expect(page.locator('[data-address-results] button').first()).toBeVisible();
  await page.locator('[data-address-results] button').first().click();
  await page.getByRole('button', { name: 'Continuer' }).click();

  const monday = page.locator('[data-hours-day="monday"]');
  await page.getByLabel('État Lundi').selectOption('slots');
  await monday.locator('[name="hours[monday][first_open]"]').fill('09:30');
  await monday.locator('[name="hours[monday][first_close]"]').fill('12:00');
  await monday.getByRole('button', { name: '+ 2ème plage' }).click();
  await monday.locator('[name="hours[monday][second_open]"]').fill('18:30');
  await monday.locator('[name="hours[monday][second_close]"]').fill('23:59');

  await expectCopyControlWithoutOverlap(page);

  await expect.poll(async () => monday.evaluate(day => {
    const secondRange = day.querySelector('[data-hours-second-slot]')?.getBoundingClientRect();
    const remove = day.querySelector('[data-remove-hours-slot]')?.getBoundingClientRect();
    return Boolean(secondRange && remove && remove.top >= secondRange.bottom && remove.left >= secondRange.left);
  })).toBe(true);
  await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
  await page.setViewportSize({ width: 768, height: 900 });
  await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
});

test('public restaurant contribution requires a cover photo and validates the email', async ({ page }, testInfo) => {
  await fillRestaurantAndAddress(page, `photos-${testInfo.project.name}-${Date.now()}`);
  await page.getByRole('button', { name: 'Continuer' }).click();
  await expect.poll(() => page.locator('[data-cover-input]').evaluate((input: HTMLInputElement) => input.validationMessage)).not.toBe('');

  await page.locator('[data-cover-input]').setInputFiles(cover);
  await page.getByRole('button', { name: 'Continuer' }).click();
  await expect(page.getByRole('heading', { name: 'Vos informations' })).toBeVisible();
  await expect(page.locator('[data-submission-summary]')).toHaveCount(0);
  expect(await page.locator('[data-owner-choice]').evaluateAll(inputs => inputs.map((input: HTMLInputElement) => input.value))).toEqual(['customer', 'owner']);
  await expect(page.getByText('Relisez la proposition avant de l’envoyer. Elle restera en attente de modération.')).toHaveCount(0);
  await page.getByLabel('Non').check();
  await expect(page.locator('[data-owner-fields]')).toBeHidden();
  await expect(page.locator('[data-owner-email-help="customer"]')).toBeVisible();
  await expect(page.locator('[name="owner_full_name"]')).not.toHaveAttribute('required', '');
  await page.getByLabel('Oui').check();
  await expect(page.locator('[data-owner-fields]')).toBeVisible();
  await expect(page.locator('[data-owner-email-help="owner"]')).toBeVisible();
  await expect(page.locator('[name="owner_full_name"]')).toHaveAttribute('required', '');
  await expect(page.locator('[name="owner_company"]')).toHaveAttribute('required', '');
  await expect(page.locator('[name="owner_siret"]')).toHaveAttribute('required', '');
  await expect(page.locator('[name="owner_certified"]')).toHaveAttribute('required', '');
  await page.getByRole('button', { name: 'Envoyer le restaurant' }).click();
  await expect.poll(() => page.locator('[name="owner_full_name"]').evaluate((input: HTMLInputElement) => input.validationMessage)).not.toBe('');
  await page.getByLabel('Non').check();
  await expect(page.locator('[data-owner-fields]')).toBeHidden();
  await expect(page.locator('[data-owner-email-help="customer"]')).toBeVisible();
  await page.getByLabel('Votre e-mail').fill('email-invalide');
  await page.getByRole('button', { name: 'Envoyer le restaurant' }).click();
  await expect.poll(() => page.getByLabel('Votre e-mail').evaluate((input: HTMLInputElement) => input.validationMessage)).not.toBe('');
});

test('public restaurant contribution presents and manages step-four photos on desktop and mobile', async ({ page }, testInfo) => {
  const errors: string[] = [];
  page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
  page.on('requestfailed', request => errors.push(`${request.method()} ${request.url()}`));
  await fillRestaurantAndAddress(page, `galerie-${testInfo.project.name}-${crypto.randomUUID()}`);

  await expect(page.getByText('Ajoutez une belle photo de couverture du restaurant.')).toBeVisible();
  await expect(page.getByLabel('Photos complémentaires (10 maximum)')).toBeVisible();
  await expect(page.getByText('Vous pourrez retirer ou réorganiser les photos avant l’envoi.')).toBeVisible();
  await expect(page.getByText(/facultatives/i)).toHaveCount(0);
  await page.locator('[data-cover-input]').setInputFiles(cover);
  await expect(page.locator('[data-cover-preview] img')).toBeVisible();

  await page.locator('[data-gallery-input]').setInputFiles([
    { ...cover, name: 'galerie-un.png' },
    { ...cover, name: 'galerie-deux.png' },
  ]);
  const gallery = page.locator('[data-gallery-preview]');
  await expect(gallery.getByRole('listitem')).toHaveCount(2);
  await gallery.getByRole('button', { name: 'Monter' }).nth(1).click();
  await expect(gallery.getByRole('listitem').first()).toContainText('galerie-deux.png');
  await gallery.getByRole('button', { name: 'Supprimer' }).first().click();
  await expect(gallery.getByRole('listitem')).toHaveCount(1);
  await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
  expect(errors).toEqual([]);
});

test('public restaurant contribution submits a pending restaurant successfully', async ({ page }, testInfo) => {
  const errors: string[] = [];
  page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
  // A timestamp differs by only a few characters between regression runs and
  // is intentionally treated as a near-name at the same address by the server.
  // Use a genuinely distinct test identity so historic preproduction fixtures
  // do not turn this non-duplicate flow into a certain duplicate.
  await fillRestaurantAndAddress(page, `complete-${testInfo.project.name}-${crypto.randomUUID()}`);
  await page.locator('[data-cover-input]').setInputFiles(cover);
  await expect(page.locator('[data-cover-preview] img')).toBeVisible();
  await page.getByRole('button', { name: 'Continuer' }).click();
  await page.getByLabel('Non').check();
  await page.getByLabel('Votre e-mail').fill(`contribution-${testInfo.project.name}@example.invalid`);
  await page.getByRole('button', { name: 'Envoyer le restaurant' }).click();
  await expect(page.getByRole('heading', { name: 'Merci pour la soumission du restaurant !' })).toBeVisible();
  await expect(page.getByText('Vous devez d’abord confirmer votre adresse e-mail. Elle ne sera jamais publiée.')).toBeVisible();
  await expect(page.getByText('Consultez votre boîte e-mail et vérifiez également vos spams pour confirmer votre adresse. Ensuite, notre équipe pourra examiner la proposition.')).toBeVisible();
  expect(errors).toEqual([]);
});
