import { expect, test } from '@playwright/test';

test('desktop submenu keeps a continuous pointer path from its parent to every child', async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== 'desktop-chromium', 'Desktop pointer-path regression.');

  await page.goto('/restaurants');

  const parent = page.locator('.nav-menu-list .nav-parent', { hasText: 'Cuisines' });
  await expect(parent).toBeVisible();
  await parent.hover();

  const panel = page.locator(`#${await parent.getAttribute('aria-controls')}`);
  await expect(panel).toBeVisible();

  const parentBox = await parent.boundingBox();
  const panelBox = await panel.boundingBox();
  expect(parentBox).not.toBeNull();
  expect(panelBox).not.toBeNull();
  if (!parentBox || !panelBox) return;

  const pointerX = panelBox.x + Math.min(24, panelBox.width / 2);
  const startY = parentBox.y + (parentBox.height / 2);
  const firstChild = panel.locator('li').first();
  const firstChildBox = await firstChild.boundingBox();
  expect(firstChildBox).not.toBeNull();
  if (!firstChildBox) return;

  const checkpoints = [
    startY,
    parentBox.y + parentBox.height + 1,
    panelBox.y + 1,
    firstChildBox.y + (firstChildBox.height / 2),
  ];

  for (const y of checkpoints) {
    await page.mouse.move(pointerX, y, { steps: 4 });
    await expect(panel).toBeVisible();
  }

  for (const label of ['test4', 'test2']) {
    const child = panel.getByText(label, { exact: true });
    await child.hover();
    await expect(child).toBeVisible();
    await expect(panel).toBeVisible();
  }

  await page.mouse.move(panelBox.x + panelBox.width + 24, panelBox.y + (panelBox.height / 2), { steps: 4 });
  await expect(panel).toBeHidden();

  await page.setViewportSize({ width: 1920, height: 900 });
  await parent.hover();
  await expect(panel).toBeVisible();
  const wideParentBox = await parent.boundingBox();
  const widePanelBox = await panel.boundingBox();
  expect(wideParentBox).not.toBeNull();
  expect(widePanelBox).not.toBeNull();
  if (!wideParentBox || !widePanelBox) return;

  await page.mouse.move(widePanelBox.x + 24, wideParentBox.y + (wideParentBox.height / 2), { steps: 4 });
  await page.mouse.move(widePanelBox.x + 24, widePanelBox.y + 1, { steps: 8 });
  await expect(panel).toBeVisible();
});

test('mobile no-link parent opens on tap and closes with Escape while retaining focus', async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== 'mobile-chromium', 'Mobile submenu interaction.');

  await page.goto('/restaurants');
  await page.getByRole('button', { name: 'Menu' }).click();

  const parent = page.locator('#mobile-nav .nav-parent', { hasText: 'Cuisines' });
  const panel = page.locator(`#${await parent.getAttribute('aria-controls')}`);
  await parent.click();
  await expect(parent).toHaveAttribute('aria-expanded', 'true');
  await expect(panel).toBeVisible();

  await page.keyboard.press('Escape');
  await expect(panel).toBeHidden();
  await expect(parent).toBeFocused();
});
