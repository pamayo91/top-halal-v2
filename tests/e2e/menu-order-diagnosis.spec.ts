import { expect, test } from '@playwright/test';

const email = process.env.PREPROD_ADMIN_EMAIL;
const password = process.env.PREPROD_ADMIN_PASSWORD;

test('drag-and-drop menu ordering uses a Laravel POST and survives a full reload', async ({ page }) => {
  test.skip(!email || !password, 'Dedicated preproduction administrator required.');

  const responses: Array<{ url: string; status: number }> = [];
  page.on('response', async (response) => {
    if (response.request().method() === 'POST') {
      responses.push({ url: response.url(), status: response.status() });
    }
  });

  await page.goto('/admin');
  await page.locator('input[type="email"]').fill(email!);
  await page.locator('input[type="password"]').fill(password!);
  await page.locator('button[type="submit"]').click();
  await expect(page).toHaveURL(/\/admin$/);
  await page.goto('/admin/menus');
  await page.getByRole('link', { name: 'Header principal', exact: true }).click();

  const branches = page.locator('.menu-editor__tree > .menu-editor__branch');
  test.skip(await branches.count() < 2, 'The configured header menu needs two root items for diagnosis.');
  const initial = await branches.evaluateAll((items) => items.map((item) => item.getAttribute('wire:key')));
  const movedLabel = await branches.nth(1).locator(':scope > .menu-editor__row strong').innerText();

  responses.length = 0;
  const firstMoveResponse = page.waitForResponse((response) => response.request().method() === 'POST' && response.url().includes('/admin/menus/'));
  await branches.nth(1).dragTo(branches.nth(0));
  await firstMoveResponse;
  await page.waitForTimeout(300);

  const afterFirstDrag = await page.locator('.menu-editor__tree > .menu-editor__branch').evaluateAll((items) => items.map((item) => item.getAttribute('wire:key')));
  try {
    expect(afterFirstDrag).not.toEqual(initial);
    expect(responses, JSON.stringify(responses)).toEqual([
      expect.objectContaining({ url: expect.stringContaining('/admin/menus/'), status: 302 }),
    ]);

    await page.goto('/');
    const publicOrder = await page.locator('.main-nav > ul > li').evaluateAll((items) => items.map((item) => item.querySelector(':scope > a, :scope > button')?.textContent?.trim()));
    expect(publicOrder[0]).toBe(movedLabel);
  } finally {
    await page.goto('/admin/menus/1/edit');
    const restoredBranches = page.locator('.menu-editor__tree > .menu-editor__branch');
    const restoreMoveResponse = page.waitForResponse((response) => response.request().method() === 'POST' && response.url().includes('/admin/menus/'));
    await restoredBranches.nth(1).dragTo(restoredBranches.nth(0));
    await restoreMoveResponse;
    await page.waitForTimeout(300);
  }

  expect(await page.locator('.menu-editor__tree > .menu-editor__branch').evaluateAll((items) => items.map((item) => item.getAttribute('wire:key')))).toEqual(initial);
});
