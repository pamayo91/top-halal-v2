import { expect, test } from '@playwright/test';

const previews = [
  '/_preview/post/27', '/_preview/post/104', '/_preview/post/295', '/_preview/post/10697', '/_preview/post/11461',
  '/_preview/page/4', '/_preview/page/5', '/_preview/page/38', '/_preview/page/10430', '/_preview/page/11554',
];

for (const path of previews) {
  test(`migrated preview ${path} renders safely`, async ({ page }) => {
    const consoleErrors: string[] = [];
    const networkErrors: string[] = [];
    page.on('console', (message) => { if (message.type() === 'error') consoleErrors.push(message.text()); });
    page.on('requestfailed', (request) => networkErrors.push(request.url()));

    const response = await page.goto(path);
    expect(response?.status()).toBe(200);
    await expect(page.locator('h1')).toBeVisible();
    expect(await page.locator('body').innerText()).not.toMatch(/\[vc_[^\]]*\]/i);
    expect(await page.locator('script').count()).toBe(0);

    for (const frame of await page.locator('iframe').all()) {
      await expect(frame).toHaveAttribute('src', /https:\/\/(www\.)?(youtube\.com|youtu\.be|vimeo\.com)\//i);
    }

    expect(consoleErrors).toEqual([]);
    expect(networkErrors).toEqual([]);
  });
}
