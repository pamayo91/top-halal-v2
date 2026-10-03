import { expect, test } from '@playwright/test';

type Payload = { urls: Record<string, string> };

const raw = process.env.REGRESSION_SENTINELS_JSON;
if (!raw) {
  throw new Error('REGRESSION_SENTINELS_JSON is required. Run the preproduction regression wrapper, not this spec directly.');
}
const sentinels = JSON.parse(raw) as Payload;

async function expectPublicFooterFlow(page: import('@playwright/test').Page, path: string, expectsShortPage: boolean): Promise<void> {
  const response = await page.goto(path);
  expect(response?.status()).toBe(200);

  const layout = await page.evaluate(() => {
    const header = document.querySelector<HTMLElement>('.site-header');
    const main = document.querySelector<HTMLElement>('main#contenu');
    const footer = document.querySelector<HTMLElement>('footer.site-footer');
    if (!header || !main || !footer) throw new Error('Public shell is incomplete.');

    const headerBox = header.getBoundingClientRect();
    const mainBox = main.getBoundingClientRect();
    const footerBox = footer.getBoundingClientRect();
    const body = getComputedStyle(document.body);
    const mainStyle = getComputedStyle(main);
    const footerStyle = getComputedStyle(footer);

    return {
      bodyDisplay: body.display,
      bodyDirection: body.flexDirection,
      bodyMinHeight: parseFloat(body.minHeight),
      mainFlexGrow: mainStyle.flexGrow,
      mainFlexShrink: mainStyle.flexShrink,
      footerFlexShrink: footerStyle.flexShrink,
      footerPosition: footerStyle.position,
      headerBottom: headerBox.bottom,
      mainTop: mainBox.top,
      mainBottom: mainBox.bottom,
      footerTop: footerBox.top,
      footerBottom: footerBox.bottom,
      viewportHeight: window.innerHeight,
      documentHeight: document.documentElement.scrollHeight,
    };
  });

  expect(layout.bodyDisplay).toBe('flex');
  expect(layout.bodyDirection).toBe('column');
  expect(layout.bodyMinHeight).toBeGreaterThanOrEqual(layout.viewportHeight - 1);
  expect(layout.mainFlexGrow).toBe('1');
  expect(layout.mainFlexShrink).toBe('0');
  expect(layout.footerFlexShrink).toBe('0');
  expect(['fixed', 'absolute']).not.toContain(layout.footerPosition);
  expect(layout.mainTop).toBeGreaterThanOrEqual(layout.headerBottom - 1);
  expect(Math.abs(layout.footerTop - layout.mainBottom)).toBeLessThanOrEqual(1);

  if (expectsShortPage) {
    expect(layout.documentHeight).toBeLessThanOrEqual(layout.viewportHeight + 1);
    expect(Math.abs(layout.footerBottom - layout.viewportHeight)).toBeLessThanOrEqual(1);
  } else {
    expect(layout.documentHeight).toBeGreaterThan(layout.viewportHeight);
  }
}

test('public footer fills the available viewport after a short authentication page', async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== 'desktop-chromium', 'The mobile authentication composition is intentionally taller than one viewport.');
  await page.setViewportSize({ width: 1440, height: 1200 });
  await expectPublicFooterFlow(page, '/login', true);
});

for (const [label, key] of [['restaurant', 'restaurant.dynamic'], ['article', 'article.dynamic']] as const) {
  test(`public footer follows the long ${label} content without an artificial gap`, async ({ page }) => {
    const path = sentinels.urls[key];
    expect(path, `${key} sentinel is required`).toBeTruthy();
    await expectPublicFooterFlow(page, path, false);
  });
}
