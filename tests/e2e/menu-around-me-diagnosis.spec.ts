import { expect, test } from '@playwright/test';

const email = process.env.PREPROD_ADMIN_EMAIL;
const password = process.env.PREPROD_ADMIN_PASSWORD;

test('diagnosis: Autour de moi can be reordered within Restaurants', async ({ page }) => {
  test.skip(!email || !password, 'Dedicated preproduction administrator required.');
  const responses: Array<{ url: string; status: number }> = [];
  page.on('response', (response) => {
    if (response.request().method() === 'POST' && response.url().includes('/admin/menus/')) responses.push({ url: response.url(), status: response.status() });
  });

  await page.goto('/admin');
  await page.locator('input[type="email"]').fill(email!);
  await page.locator('input[type="password"]').fill(password!);
  await page.locator('button[type="submit"]').click();
  await expect(page).toHaveURL(/\/admin$/);
  await page.goto('/admin/menus/1/edit');

  const around = page.getByText('Autour de moi', { exact: true }).locator('xpath=ancestor::div[contains(@class, "menu-editor__branch")][1]');
  const paris = page.getByText('Restos Paris', { exact: true }).locator('xpath=ancestor::div[contains(@class, "menu-editor__branch")][1]');
  const initial = await page.locator('.menu-editor__branch').evaluateAll((rows) => rows.map((row) => row.getAttribute('wire:key')));

  responses.length = 0;
  const response = page.waitForResponse((result) => result.request().method() === 'POST' && result.url().includes('/admin/menus/'));
  await around.dragTo(paris);
  await response;
  await page.waitForTimeout(1_000);
  await page.goto('/admin/menus/1/edit');

  try {
    expect(responses).toEqual([expect.objectContaining({ status: 302 })]);
  } finally {
    const currentAround = page.getByText('Autour de moi', { exact: true }).locator('xpath=ancestor::div[contains(@class, "menu-editor__branch")][1]');
    const currentParis = page.getByText('Restos Paris', { exact: true }).locator('xpath=ancestor::div[contains(@class, "menu-editor__branch")][1]');
    const restore = page.waitForResponse((result) => result.request().method() === 'POST' && result.url().includes('/admin/menus/'));
    await currentParis.dragTo(currentAround);
    await restore;
    await page.waitForTimeout(1_000);
    await page.goto('/admin/menus/1/edit');
  }

  expect(await page.locator('.menu-editor__branch').evaluateAll((rows) => rows.map((row) => row.getAttribute('wire:key')))).toEqual(initial);
});
