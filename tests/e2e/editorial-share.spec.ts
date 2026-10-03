import { expect, test } from '@playwright/test';

test('editorial share buttons preserve their destinations and accessible visual states', async ({ page }) => {
  const consoleErrors: string[] = [];
  const networkFailures: string[] = [];
  page.on('console', message => {
    if (message.type() === 'error') consoleErrors.push(message.text());
  });
  page.on('requestfailed', request => networkFailures.push(`${request.method()} ${request.url()}`));

  await page.goto('/blog');
  const article = page.locator('.article-card h2 a').first();
  await expect(article).toBeVisible();
  await article.click();

  const share = page.locator('.sidebar-share:visible');
  await expect(share).toBeVisible();
  const facebook = share.getByRole('link', { name: 'Partager sur Facebook' });
  const x = share.getByRole('link', { name: 'Partager sur X' });
  const email = share.getByRole('link', { name: 'Partager par e-mail' });
  const sharedUrl = page.url();

  for (const link of [facebook, x, email]) {
    await expect(link).toHaveJSProperty('offsetWidth', 40);
    await expect(link).toHaveJSProperty('offsetHeight', 40);
    await expect(link).toHaveCSS('border-top-width', '0px');
    await expect(link.locator('svg')).toHaveAttribute('aria-hidden', 'true');
  }

  expect(new URL((await facebook.getAttribute('href'))!).searchParams.get('u')).toBe(sharedUrl);
  expect(new URL((await x.getAttribute('href'))!).searchParams.get('url')).toBe(sharedUrl);
  const emailHref = await email.getAttribute('href');
  expect(emailHref).toContain(`body=${encodeURIComponent(sharedUrl)}`);
  expect(emailHref).toContain(`subject=${encodeURIComponent((await page.locator('h1').innerText()).trim())}`);

  await facebook.hover();
  await expect(facebook).toHaveCSS('background-color', 'rgb(24, 119, 242)');
  await expect(facebook).toHaveCSS('color', 'rgb(255, 255, 255)');
  await x.focus();
  await expect(x).toHaveCSS('background-color', 'rgb(0, 0, 0)');
  await expect(x).toHaveCSS('color', 'rgb(255, 255, 255)');
  await expect(x).toHaveCSS('outline-style', 'solid');
  await email.hover();
  await expect(email).toHaveCSS('background-color', 'rgb(8, 112, 90)');
  await expect(email).toHaveCSS('color', 'rgb(255, 255, 255)');

  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBeTruthy();
  expect(consoleErrors).toEqual([]);
  expect(networkFailures).toEqual([]);
});
