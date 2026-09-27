import { expect, test } from '@playwright/test';

test('a long desktop table of contents compacts and can be expanded again', async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== 'desktop-chromium', 'The compact table of contents is desktop-only.');

  await page.goto('/');
  const scriptPath = await page.locator('script[type="module"][src*="/build/assets/"]').first().getAttribute('src');
  const stylePaths = await page.locator('link[rel="stylesheet"][href*="/build/assets/"]').evaluateAll(links => links.map(link => link.getAttribute('href')));
  expect(scriptPath).toBeTruthy();
  expect(stylePaths).not.toEqual([]);
  const scriptSrc = new URL(scriptPath!, page.url()).href;
  const styles = stylePaths.map(path => `<link rel="stylesheet" href="${new URL(path!, page.url()).href}">`).join('');

  const headings = Array.from({ length: 20 }, (_, index) => `<h2 id="section-${index + 1}" style="height:140px">Section ${index + 1}</h2>`).join('');
  const links = Array.from({ length: 20 }, (_, index) => `<li class="toc-level-2"><a href="#section-${index + 1}">Section ${index + 1}</a></li>`).join('');
  await page.setContent(`${styles}<section class="sidebar-card sidebar-toc" data-sticky-toc><div class="sidebar-toc-header"><p class="sidebar-title">Sommaire</p><button class="sidebar-toc-collapse" type="button" data-toc-collapse hidden>Réduire <span aria-hidden="true">↑</span></button></div><div class="sidebar-toc-current" data-toc-current hidden><a data-toc-current-link href="#section-1">Section 1</a></div><button class="sidebar-toc-toggle" type="button" data-toc-toggle aria-expanded="false" hidden>Afficher le sommaire</button><ol data-toc-list>${links}</ol></section>${headings}`);
  await page.evaluate(src => new Promise<void>((resolve, reject) => {
    const script = document.createElement('script');
    const fixtureSrc = new URL(src); fixtureSrc.searchParams.set('fixture', 'sticky-toc');
    script.type = 'module'; script.src = fixtureSrc.href; script.onload = () => resolve(); script.onerror = () => reject(new Error('Unable to load the public application script.'));
    document.head.append(script);
  }), scriptSrc!);

  const toc = page.locator('[data-sticky-toc]');
  const list = toc.locator('[data-toc-list]');
  const toggle = toc.locator('[data-toc-toggle]');
  const collapse = toc.locator('[data-toc-collapse]');
  await expect(toggle).toBeHidden();
  await expect(collapse).toBeHidden();
  await expect(list).toBeVisible();
  expect(await toc.evaluate(element => Number.parseFloat(getComputedStyle(element).maxHeight))).toBeLessThanOrEqual(900 * .68);

  await page.evaluate(() => window.scrollTo(0, 2_200));
  await expect(toc).toHaveClass(/is-compact/);
  await expect(toc.locator('[data-toc-current-link]')).not.toHaveText('Section 1');
  await expect(list).toBeHidden();
  await expect(toggle).toBeVisible();

  await toggle.click();
  await expect(toggle).toBeHidden();
  await expect(collapse).toBeVisible();
  await expect(list).toBeVisible();
  await collapse.click();
  await expect(list).toBeHidden();
  await expect(toggle).toBeVisible();
});
