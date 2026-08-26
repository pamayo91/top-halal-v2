import { expect, test } from '@playwright/test';

test('preproduction homepage and health endpoint respond cleanly', async ({ page, request }) => {
  const consoleErrors: string[] = [];
  const networkErrors: string[] = [];
  const insecureRequests: string[] = [];

  page.on('console', (message) => {
    if (message.type() === 'error') {
      consoleErrors.push(message.text());
    }
  });
  page.on('requestfailed', (request) => {
    networkErrors.push(`${request.method()} ${request.url()} ${request.failure()?.errorText ?? ''}`);
  });
  page.on('request', (request) => {
    const url = request.url();

    if (url.startsWith('http://')) {
      insecureRequests.push(url);
    }
  });

  const response = await page.goto('/');
  expect(response?.status()).toBe(200);
  await expect(page.getByRole('heading', { name: 'Trouvez votre restaurant halal, simplement.' })).toBeVisible();

  const health = await request.get('/health');
  await expect(health).toBeOK();
  expect(health.headers()['x-robots-tag']).toBe('noindex, nofollow');
  expect(await health.json()).toEqual({
    status: 'ok',
    service: 'top-halal-v2',
  });

  const cookies = await page.context().cookies();
  const sessionCookie = cookies.find((cookie) => cookie.name === 'top-halal-v2-session');
  expect(sessionCookie).toBeDefined();
  expect(sessionCookie?.secure).toBe(true);
  expect(sessionCookie?.httpOnly).toBe(true);
  expect(sessionCookie?.sameSite).toBe('Lax');

  expect(consoleErrors).toEqual([]);
  expect(networkErrors).toEqual([]);
  expect(insecureRequests).toEqual([]);
});

test('preproduction 404 and sensitive paths are not exposed', async ({ request }) => {
  const missing = await request.get('/__codex_missing_404');
  expect(missing.status()).toBe(404);
  expect(missing.headers()['x-robots-tag']).toBe('noindex, nofollow');

  for (const path of ['/.env', '/composer.json', '/artisan', '/storage/', '/vendor/', '/.git/']) {
    const response = await request.get(path, { maxRedirects: 5 });

    expect(response.status(), `${path} must not be publicly readable`).toBeGreaterThanOrEqual(400);
    expect(response.status(), `${path} must not be publicly readable`).not.toBe(200);
  }
});

test('preproduction robots and http to https protection are active', async ({ request, baseURL }) => {
  const robots = await request.get('/robots.txt');
  await expect(robots).toBeOK();
  expect(robots.headers()['x-robots-tag']).toBe('noindex, nofollow');
  expect(await robots.text()).toContain('Disallow: /');

  const url = new URL(baseURL ?? '');
  const httpUrl = `http://${url.host}/`;
  const redirect = await request.get(httpUrl, { maxRedirects: 0 });

  expect(redirect.status()).toBe(301);
  expect(redirect.headers().location).toBe(`https://${url.host}/`);
});
