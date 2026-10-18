import { expect, test } from '@playwright/test';

const email = process.env.PREPROD_ADMIN_EMAIL;
const password = process.env.PREPROD_ADMIN_PASSWORD;
const consentCookie = 'top_halal_analytics_consent_v1';
const standardChrome = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36';

test.describe.configure({ mode: 'serial' });
test.describe('Consentement Analytics', () => {
  test.use({ userAgent: standardChrome });
  test.skip(!email || !password, 'PREPROD_ADMIN_EMAIL and PREPROD_ADMIN_PASSWORD are required.');

  test('reste first-party, réversible et utilisable au clavier sur desktop et mobile', async ({ page, context }, testInfo) => {
    const consoleErrors: string[] = [];
    const failedRequests: string[] = [];
    const requests: string[] = [];
    page.on('console', message => { if (message.type() === 'error') consoleErrors.push(message.text()); });
    page.on('requestfailed', request => failedRequests.push(`${request.method()} ${request.url()}`));
    page.on('request', request => requests.push(request.url()));

    await page.goto('/admin');
    await page.getByLabel(/Adresse e-mail/).fill(email!);
    await page.getByLabel(/Mot de passe/).fill(password!);
    await page.getByRole('button', { name: 'Connexion' }).click({ noWaitAfter: true });
    await expect(page).toHaveURL(/\/admin$/);
    await page.goto('/admin/settings');

    const enabled = page.locator('#form\\.analytics_ga4_enabled');
    const measurementId = page.getByRole('textbox', { name: 'ID de mesure GA4' });
    const save = page.getByRole('button', { name: 'Enregistrer', exact: true });
    const isEnabled = async () => (await enabled.getAttribute('aria-checked')) === 'true';
    const setEnabled = async (value: boolean) => { if ((await isEnabled()) !== value) await enabled.click({ noWaitAfter: true }); };
    const originalEnabled = await isEnabled();
    const originalMeasurementId = await measurementId.inputValue();

    try {
      await setEnabled(false);
      await measurementId.fill('');
      await save.click({ noWaitAfter: true });
      await expect(page.getByText('Réglages enregistrés', { exact: true })).toBeVisible();
      await page.goto('/');
      await expect(page.getByText("Mesure d'audience")).toHaveCount(0);
      await expect(page.getByRole('button', { name: 'Gérer les cookies' })).toHaveCount(0);

      await page.goto('/admin/settings');
      await setEnabled(true);
      await measurementId.fill('G-AB12CD34EF');
      await save.click({ noWaitAfter: true });
      await expect(page.getByText('Réglages enregistrés', { exact: true })).toBeVisible();

      await context.clearCookies();
      await page.goto('/');
      const banner = page.locator('[data-analytics-consent-banner]');
      await expect(banner).toBeVisible();
      await expect(banner.getByRole('button', { name: 'Refuser' })).toBeVisible();
      await expect(banner.getByRole('button', { name: 'Accepter' })).toBeVisible();
      expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBeTruthy();
      await banner.getByRole('button', { name: 'Refuser' }).focus();
      await page.keyboard.press('Tab');
      await expect(banner.getByRole('button', { name: 'Accepter' })).toBeFocused();
      await banner.getByRole('button', { name: 'Refuser' }).click();
      await expect(banner).toHaveCount(0);
      expect((await context.cookies()).find(cookie => cookie.name === consentCookie)).toMatchObject({ value: 'denied', path: '/', sameSite: 'Lax', secure: true });

      await page.reload();
      await expect(banner).toHaveCount(0);
      const manage = page.getByRole('button', { name: 'Gérer les cookies' });
      await manage.click();
      const dialog = page.locator('[data-analytics-consent-dialog]');
      await expect(dialog).toBeVisible();
      await expect(dialog.getByText('Votre choix actuel : refusé.')).toBeVisible();
      await dialog.getByRole('button', { name: 'Accepter' }).click();
      await expect(dialog).not.toBeVisible();
      await expect(manage).toBeFocused();
      expect((await context.cookies()).find(cookie => cookie.name === consentCookie)).toMatchObject({ value: 'granted', path: '/', sameSite: 'Lax', secure: true });

      await context.addCookies([{ name: consentCookie, value: 'invalid', url: page.url(), secure: true, sameSite: 'Lax' }]);
      await page.reload();
      await expect(banner).toBeVisible();
      await page.evaluate(() => ({ dataLayer: 'dataLayer' in window, gtag: 'gtag' in window }));
      expect(await page.evaluate(() => ({ dataLayer: 'dataLayer' in window, gtag: 'gtag' in window }))).toEqual({ dataLayer: false, gtag: false });
      expect(requests.filter(url => /google-analytics\.com|googletagmanager\.com/i.test(url))).toEqual([]);
      expect(consoleErrors).toEqual([]);
      expect(failedRequests).toEqual([]);
    } finally {
      await page.goto('/admin/settings');
      await setEnabled(originalEnabled);
      await measurementId.fill(originalMeasurementId);
      await save.click({ noWaitAfter: true });
      await expect(page.getByText('Réglages enregistrés', { exact: true })).toBeVisible();
    }
  });
});
