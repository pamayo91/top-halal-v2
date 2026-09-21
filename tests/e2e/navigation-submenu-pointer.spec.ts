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

test('header states have an obvious stable visual hierarchy', async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== 'desktop-chromium', 'Desktop visual state assertions.');

  await page.goto('/restaurants');
  const restaurants = page.locator('.main-nav .nav-item', { hasText: 'Restaurants' });
  const cuisines = page.locator('.main-nav .nav-parent', { hasText: 'Cuisines' });
  const blog = page.locator('.main-nav .nav-item', { hasText: 'Blog' });

  const normal = await blog.evaluate(element => {
    const style = getComputedStyle(element);
    const marker = getComputedStyle(element, '::after');
    return { color: style.color, weight: style.fontWeight, decoration: style.textDecorationLine, markerOpacity: marker.opacity, markerHeight: marker.height };
  });
  expect(normal.decoration).toBe('none');
  expect(normal.markerOpacity).toBe('0');
  expect(normal.weight).toBe('600');

  await blog.hover();
  const hover = await blog.evaluate(element => { const style = getComputedStyle(element); const marker = getComputedStyle(element, '::after'); return { color: style.color, markerColor: marker.backgroundColor, markerHeight: marker.height, markerOpacity: marker.opacity }; });
  expect(hover.color).toBe('rgb(7, 68, 54)');
  expect(hover.markerColor).toBe('rgb(11, 93, 75)');
  expect(hover.markerHeight).toBe('2px');
  expect(hover.markerOpacity).toBe('1');

  const active = await restaurants.evaluate(element => { const style = getComputedStyle(element); const marker = getComputedStyle(element, '::after'); return { color: style.color, weight: style.fontWeight, markerHeight: marker.height, markerOpacity: marker.opacity }; });
  expect(active.color).toBe('rgb(11, 93, 75)');
  expect(active.weight).toBe('700');
  expect(active.markerHeight).toBe('3px');
  expect(active.markerOpacity).toBe('1');

  await restaurants.hover();
  await cuisines.hover();
  const activeHover = await restaurants.evaluate(element => getComputedStyle(element, '::after').height);
  const parentHover = await cuisines.evaluate(element => { const style = getComputedStyle(element); const marker = getComputedStyle(element, '::after'); return { color: style.color, markerHeight: marker.height, markerOpacity: marker.opacity }; });
  expect(activeHover).toBe('3px');
  expect(parentHover.color).toBe('rgb(7, 68, 54)');
  expect(parentHover.markerHeight).toBe('2px');
  expect(parentHover.markerOpacity).toBe('1');
});
