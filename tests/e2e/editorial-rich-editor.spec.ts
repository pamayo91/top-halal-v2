import { expect, test } from '@playwright/test';

const email = process.env.PREPROD_ADMIN_EMAIL;
const password = process.env.PREPROD_ADMIN_PASSWORD;

test.describe('Editorial rich editor', () => {
  test.skip(!email || !password, 'PREPROD_ADMIN_EMAIL and PREPROD_ADMIN_PASSWORD are required.');

  test('creates, reopens and preserves SEO link attributes while keeping its toolbar sticky', async ({ page }) => {
    const consoleErrors: string[] = [];
    page.on('console', (message) => {
      if (message.type() === 'error' && !message.text().startsWith('Failed to load resource:')) consoleErrors.push(message.text());
    });

    await page.goto('/admin');
    await page.locator('input[type="email"]').fill(email!);
    await page.locator('input[type="password"]').fill(password!);
    await page.locator('button[type="submit"]').click();
    await expect(page).toHaveURL(/\/admin$/);

    await page.goto('/admin/articles/create');
    await page.getByLabel('Titre').fill(`Validation éditeur ${Date.now()}`);
    const editor = page.locator('.editorial-rich-editor .tiptap');
    await editor.click();
    await page.keyboard.insertText('Lien SEO');
    await page.keyboard.press('Control+A');
    await page.getByRole('button', { name: 'Lien', exact: true }).click();

    const dialog = page.getByRole('dialog');
    await dialog.getByLabel('URL').fill('https://example.com');
    await dialog.getByLabel('Nofollow').check();
    await dialog.getByLabel('Sponsored').check();
    await dialog.getByLabel('UGC').check();
    await dialog.getByLabel('Ouvrir dans un nouvel onglet').check();
    await dialog.getByRole('button', { name: 'Enregistrer', exact: true }).click();

    const link = editor.locator('a');
    await expect(link).toHaveAttribute('rel', /nofollow sponsored ugc/);
    await expect(link).toHaveAttribute('target', '_blank');

    await link.click();
    await page.getByRole('button', { name: 'Lien', exact: true }).click();
    await expect(dialog.getByLabel('Nofollow')).toBeChecked();
    await expect(dialog.getByLabel('Sponsored')).toBeChecked();
    await expect(dialog.getByLabel('UGC')).toBeChecked();
    await expect(dialog.getByLabel('Ouvrir dans un nouvel onglet')).toBeChecked();
    await page.keyboard.press('Escape');

    await editor.click();
    for (let paragraph = 0; paragraph < 40; paragraph++) {
      await page.keyboard.press('End');
      await page.keyboard.press('Enter');
      await page.keyboard.insertText(`Paragraphe de validation ${paragraph} `.repeat(12));
    }
    await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
    const stickyGeometry = await page.locator('.editorial-rich-editor .fi-fo-rich-editor-toolbar').evaluate((toolbar) => {
      const header = document.querySelector('.fi-topbar-ctn');
      return { toolbarTop: toolbar.getBoundingClientRect().top, headerBottom: header?.getBoundingClientRect().bottom ?? 0 };
    });
    expect(stickyGeometry.toolbarTop).toBeGreaterThanOrEqual(stickyGeometry.headerBottom - 1);
    expect(stickyGeometry.toolbarTop).toBeLessThanOrEqual(stickyGeometry.headerBottom + 1);
    expect(consoleErrors).toEqual([]);
  });
});
