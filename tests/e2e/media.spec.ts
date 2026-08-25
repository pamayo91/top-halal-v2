import { expect, test } from '@playwright/test';

for (const path of ['/_preview/post/11461', '/_preview/page/10430', '/_preview/page/11554', '/_preview/restaurant/13453']) {
  test(`pilot media ${path} is served by V2 with stable dimensions`, async ({ page }) => {
    const requests: string[] = [];
    const failures: string[] = [];
    page.on('request', request => requests.push(request.url()));
    page.on('requestfailed', request => failures.push(request.url()));

    const response = await page.goto(path);
    expect(response?.status()).toBe(200);
    const image = page.locator('img').first();
    await expect(image).toBeVisible();
    await expect(image).toHaveAttribute('src', /^https?:\/\/[^/]+\/media\/\d+$/);
    await expect(image).toHaveAttribute('width', /\d+/);
    await expect(image).toHaveAttribute('height', /\d+/);
    expect(requests.some(url => /top-halal\.fr\/wp-conten(?:t|u)/i.test(url))).toBeFalsy();
    expect(failures).toEqual([]);
  });
}
