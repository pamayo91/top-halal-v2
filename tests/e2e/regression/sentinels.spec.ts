import { expect, test } from '@playwright/test';

type Payload = { urls: Record<string, string>; media_urls: string[] };

const raw = process.env.REGRESSION_SENTINELS_JSON;
if (!raw) {
  throw new Error('REGRESSION_SENTINELS_JSON is required. Run the preproduction regression wrapper, not this spec directly.');
}
const sentinels = JSON.parse(raw) as Payload;

for (const [key, path] of Object.entries(sentinels.urls)) {
  test(`sentinel ${key} has its expected HTTP status and no browser failures`, async ({ page }) => {
    const consoleErrors: string[] = [];
    const networkFailures: string[] = [];
    const legacyRequests: string[] = [];
    page.on('console', message => {
      const expectedNotFound = key === 'not_found' && /Failed to load resource:.*404/i.test(message.text());
      if (message.type() === 'error' && !expectedNotFound) consoleErrors.push(message.text());
    });
    page.on('requestfailed', request => networkFailures.push(`${request.method()} ${request.url()}`));
    page.on('request', request => { if (/wp-conten(?:t|u)/i.test(request.url())) legacyRequests.push(request.url()); });

    if (key === 'redirect.representative') {
      const redirect = await page.request.get(path, { maxRedirects: 0 });
      expect(redirect.status()).toBe(301);
      const response = await page.goto(path);
      expect(response?.status(), `${key} must never return 500`).not.toBe(500);
    } else {
      const response = await page.goto(path);
      expect(response?.status(), `${key} must never return 500`).not.toBe(500);
      expect(response?.status()).toBe(key === 'not_found' ? 404 : 200);
    }
    expect(consoleErrors).toEqual([]);
    expect(networkFailures).toEqual([]);
    expect(legacyRequests).toEqual([]);
  });
}

for (const path of sentinels.media_urls) {
  test(`sentinel media ${path} remains readable from V2 storage`, async ({ request }) => {
    const response = await request.get(path);
    expect(response.status()).toBe(200);
    expect(response.headers()['content-type']).toMatch(/^image\//);
  });
}
