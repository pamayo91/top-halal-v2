import { expect, test } from '@playwright/test';

const email = process.env.PREPROD_ADMIN_EMAIL;
const password = process.env.PREPROD_ADMIN_PASSWORD;

test('diagnosis: drag-and-drop menu ordering exposes its transport response', async ({ page }) => {
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

  responses.length = 0;
  await branches.nth(1).dragTo(branches.nth(0));
  await page.waitForTimeout(500);
  await page.reload();
  const afterFirstDrag = await page.locator('.menu-editor__tree > .menu-editor__branch').evaluateAll((items) => items.map((item) => item.getAttribute('wire:key')));

  // Restore the original production-preparation state, regardless of the result under diagnosis.
  if (afterFirstDrag.join(',') !== initial.join(',')) {
    const restoredBranches = page.locator('.menu-editor__tree > .menu-editor__branch');
    await restoredBranches.nth(1).dragTo(restoredBranches.nth(0));
    await page.waitForTimeout(500);
    await page.reload();
  }

  expect(afterFirstDrag).not.toEqual(initial);
  expect(responses, JSON.stringify(responses)).toEqual([
    expect.objectContaining({ url: expect.stringContaining('/admin/menus/'), status: 302 }),
  ]);
  expect(await page.locator('.menu-editor__tree > .menu-editor__branch').evaluateAll((items) => items.map((item) => item.getAttribute('wire:key')))).toEqual(initial);
});
