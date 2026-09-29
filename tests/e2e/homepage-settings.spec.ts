import { expect, test } from '@playwright/test';

const email = process.env.PREPROD_ADMIN_EMAIL;
const password = process.env.PREPROD_ADMIN_PASSWORD;

test('homepage keeps its ordered SSR content and search', async ({ page }, testInfo) => {
  const errors: string[] = []; const failed: string[] = [];
  page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
  page.on('requestfailed', request => failed.push(request.url()));
  await page.goto('/');
  await expect(page.getByRole('heading', { name: 'Trouvez votre restaurant halal, simplement.' })).toBeVisible();
  for (const title of ['Trouvez facilement un restaurant halal', 'Le halal au quotidien, et bien plus encore', 'Des informations pour mieux choisir']) {
    const heading = page.getByRole('heading', { name: title });
    expect(await heading.evaluate(element => {
      element.scrollIntoView({ block: 'center' });
      const style = getComputedStyle(element);
      return !element.closest('[hidden]') && style.display !== 'none' && style.visibility !== 'hidden' && style.opacity !== '0';
    })).toBeTruthy();
  }
  await expect(page.getByRole('link', { name: 'Ajouter un restaurant' }).last()).toHaveAttribute('href', /\/ajouter-un-restaurant$/);
  const restaurantCards = page.locator('.home-restaurant-grid > .restaurant-card');
  await expect(restaurantCards).toHaveCount(8);
  await expect(page.locator('.home-explore-panel').first().locator('.link-list > li')).toHaveCount(10);
  await expect(page.locator('.home-explore-icon svg')).toHaveCount(2);
  if (testInfo.project.name === 'desktop-chromium') expect((await page.locator('.home-restaurant-grid').evaluate(grid => getComputedStyle(grid).gridTemplateColumns.split(' ').length))).toBe(4);
  if (testInfo.project.name === 'desktop-chromium') {
    const trustCards = await page.locator('.home-why-grid > article').evaluateAll(cards => cards.map(card => ({ height: Math.round(card.getBoundingClientRect().height), top: Math.round(card.getBoundingClientRect().top) })));
    expect(trustCards.every(card => card.height === trustCards[0].height && card.top === trustCards[0].top)).toBeTruthy();
  }
  if (testInfo.project.name === 'mobile-chromium') {
    await expect(page.locator('.home-restaurants .home-restaurant-grid')).toHaveCount(1);
    expect(await page.locator('.home-restaurants .home-restaurant-grid').evaluate(grid => grid.scrollWidth > grid.clientWidth)).toBeTruthy();
    expect(await page.locator('.home-guide-cards .home-guide-grid').evaluate(grid => grid.scrollWidth > grid.clientWidth)).toBeTruthy();
    const iconSizes = await page.locator('.home-explore-icon svg, .home-why-icon svg, .home-transparency-icon svg').evaluateAll(icons => icons.map(icon => {
      const bounds = icon.getBoundingClientRect(); return { width: bounds.width, height: bounds.height };
    }));
    expect(iconSizes.every(icon => icon.width <= 20 && icon.height <= 20)).toBeTruthy();
    expect(await page.locator('.home-explore-panel .link-list a').evaluateAll(links => links.every(link => getComputedStyle(link).borderBottomWidth === '0px'))).toBeTruthy();
    const [transparency, cta] = await Promise.all([
      page.locator('.home-transparency-banner').evaluate(element => element.getBoundingClientRect().height),
      page.locator('.home-submission-cta').evaluate(element => element.getBoundingClientRect().height),
    ]);
    expect(cta).toBeGreaterThanOrEqual(transparency * .7);
    const titleLines = await page.locator('.home-submission-cta h2').evaluate(element => {
      const style = getComputedStyle(element);
      return element.getBoundingClientRect().height / Number.parseFloat(style.lineHeight);
    });
    expect(titleLines).toBeGreaterThanOrEqual(1.9);
    expect(titleLines).toBeLessThan(2.2);
  }
  expect(await page.locator('[data-home-section]').evaluateAll(sections => sections.map(section => section.getAttribute('data-home-section')))).toEqual(['hero', 'restaurants', 'restaurant-editorial', 'explore', 'editorial', 'guide-intro', 'guide-cards', 'why', 'transparency', 'cta']);
  await expect(page.locator('.home-explore-panel')).toHaveCount(2);
  await expect(page.locator('.home-explore-panel .home-explore-icon')).toHaveCount(2);
  await expect(page.locator('.home-explore-media-slot')).toHaveCount(2);
  await expect(page.locator('.home-explore-illustration')).toHaveCount(0);
  await expect(page.locator('.home-why-grid > article')).toHaveCount(4);
  await expect(page.locator('.home-transparency-banner')).toBeVisible();
  await expect(page.locator('[data-home-section="cta"] .home-submission-cta')).toBeVisible();
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
