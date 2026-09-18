import { expect, test } from '@playwright/test';

test('published restaurant exposes only opaque outbound actions and redirects them', async ({ page }) => {
  const errors: string[] = [];
  page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
  page.on('requestfailed', request => errors.push(`${request.method()} ${request.url()}`));

  await page.goto('/resto/test1-kws358jp');
  await expect(page.getByRole('heading', { name: 'test1' })).toBeVisible();

  const actions = page.locator('[aria-label="Liens externes du restaurant"] button[type="submit"]');
  await expect(actions).toHaveCount(2);
  await expect(actions).toHaveText(['Site web', 'Facebook']);
  const forms = page.locator('[aria-label="Liens externes du restaurant"] form');
  await expect(forms).toHaveCount(2);
  for (const form of await forms.all()) await expect(form).toHaveAttribute('action', 'https://dev.top-halal.fr/sortie');

  const html = await page.content();
  expect(html).not.toContain('foo.fr');
  expect(html).not.toContain('www.facebook.com');
  expect(html).not.toContain('<a href="https://dev.top-halal.fr/sortie/');
  expect(html).not.toContain('<a href="/sortie/');
  const jsonLd = (await page.locator('script[type="application/ld+json"]').allTextContents()).join('\n');
  expect(jsonLd).not.toContain('foo.fr');
  expect(jsonLd).not.toContain('www.facebook.com');

  const expectedHosts = ['foo.fr', 'www.facebook.com'];
  for (const [index, host] of expectedHosts.entries()) {
    const responsePromise = page.waitForResponse(response => new URL(response.url()).pathname === '/sortie' && response.request().method() === 'POST');
    await actions.nth(index).click();
    const response = await responsePromise;
    expect(response.status()).toBe(303);
    expect(new URL(response.headers().location!).host).toBe(expectedHosts[index]);
    await expect.poll(() => new URL(page.url()).host).toBe(host);

    if (index < expectedHosts.length - 1) {
      await page.goto('/resto/test1-kws358jp');
      await expect(actions).toHaveCount(2);
    }
  }

  await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
  expect(errors).toEqual([]);
});
