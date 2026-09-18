import { expect, test } from '@playwright/test';

test('published restaurant exposes only opaque outbound actions and redirects them', async ({ page, request }) => {
  const errors: string[] = [];
  page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
  page.on('requestfailed', request => errors.push(`${request.method()} ${request.url()}`));

  await page.goto('/resto/test1-kws358jp');
  await expect(page.getByRole('heading', { name: 'test1' })).toBeVisible();

  const actions = page.locator('[aria-label="Liens externes du restaurant"] a');
  await expect(actions).toHaveCount(2);
  await expect(actions).toHaveText(['Site web', 'Facebook']);
  for (const action of await actions.all()) {
    await expect(action).toHaveAttribute('href', /^\/sortie\/[A-Za-z0-9_-]{20,64}$/);
  }

  const html = await page.content();
  expect(html).not.toContain('foo.fr');
  expect(html).not.toContain('www.facebook.com');
  const jsonLd = await page.locator('script[type="application/ld+json"]').textContent();
  expect(jsonLd).not.toContain('foo.fr');
  expect(jsonLd).not.toContain('www.facebook.com');

  const expectedHosts = ['foo.fr', 'www.facebook.com'];
  for (const [index, href] of (await actions.evaluateAll(items => items.map(item => (item as HTMLAnchorElement).getAttribute('href')!))).entries()) {
    const response = await request.get(href, { maxRedirects: 0 });
    expect(response.status()).toBe(302);
    expect(new URL(response.headers().location!).host).toBe(expectedHosts[index]);
  }

  await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
  expect(errors).toEqual([]);
});
