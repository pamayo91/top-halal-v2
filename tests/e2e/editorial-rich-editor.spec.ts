import { expect, test } from '@playwright/test';

const email = process.env.PREPROD_ADMIN_EMAIL;
const password = process.env.PREPROD_ADMIN_PASSWORD;

test.describe('Editorial rich editor', () => {
  test.skip(!email || !password, 'PREPROD_ADMIN_EMAIL and PREPROD_ADMIN_PASSWORD are required.');

  test('opens and preserves SEO link attributes while keeping its toolbar sticky', async ({ page }) => {
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
    await page.getByRole('textbox', { name: 'Title*', exact: true }).fill(`Validation éditeur ${Date.now()}`);
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
    await dialog.getByRole('button', { name: 'Soumettre', exact: true }).click();

    const link = editor.locator('a');
    await expect(link).toHaveAttribute('rel', /nofollow sponsored ugc/);
    await expect(link).toHaveAttribute('target', '_blank');

    await link.click();
    await page.getByRole('button', { name: 'Lien', exact: true }).click();
    await expect(dialog.getByLabel('URL')).toHaveValue('https://example.com');
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

  test('reads each existing link URL in the reported historical article without editing it', async ({ page }) => {
    await page.goto('/admin');
    await page.locator('input[type="email"]').fill(email!);
    await page.locator('input[type="password"]').fill(password!);
    await page.locator('button[type="submit"]').click();
    await expect(page).toHaveURL(/\/admin$/);

    await page.goto('/admin/articles/114/edit');
    const expectedLinks = [
      {
        href: 'https://pole-autoentrepreneur.com/guide/creer-son-auto-entreprise/devenir-auto-entrepreneur/',
        name: 'devenir auto entrepreneur',
      },
      {
        href: 'https://www.juniorwaterprize.fr/livreur-uber-eats-en-auto-entrepreneur/',
        name: 'auto entrepreneur uber eats',
      },
    ];

    for (const expectedLink of expectedLinks) {
      const link = page.getByRole('link', { name: expectedLink.name, exact: true });
      await expect(link).toHaveAttribute('href', expectedLink.href);
      await expect(link.locator('a')).toHaveCount(0);
    }

    await page.getByRole('button', { name: 'Code source', exact: true }).click();
    const source = page.getByRole('dialog').getByLabel('HTML');

    for (const expectedLink of expectedLinks) {
      await expect(source).toHaveValue(new RegExp(`href="${expectedLink.href}"`));
    }

    await page.getByRole('dialog').getByRole('button', { name: 'Annuler', exact: true }).click();
  });

  test('opens and applies the shared HTML source editor without bypassing sanitization', async ({ page }) => {
    await page.goto('/admin');
    await page.locator('input[type="email"]').fill(email!);
    await page.locator('input[type="password"]').fill(password!);
    await page.locator('button[type="submit"]').click();
    await expect(page).toHaveURL(/\/admin$/);

    await page.goto('/admin/articles/create');
    await page.getByRole('textbox', { name: 'Title*', exact: true }).fill(`Source HTML ${Date.now()}`);
    const editor = page.locator('.editorial-rich-editor .tiptap');
    await editor.click();
    await page.keyboard.insertText('État initial');

    await page.getByRole('button', { name: 'Code source', exact: true }).click();
    const dialog = page.getByRole('dialog');
    const source = dialog.getByLabel('HTML');
    await expect(source).toHaveValue(/État initial/);
    await dialog.getByRole('button', { name: 'Annuler', exact: true }).click();
    await expect(editor).toContainText('État initial');

    await page.getByRole('button', { name: 'Code source', exact: true }).click();
    await dialog.getByLabel('HTML').fill('<p>HTML appliqué</p><script>alert(1)</script>');
    await dialog.getByRole('button', { name: 'Appliquer', exact: true }).click();
    await expect(editor).toContainText('HTML appliqué');
    await expect(editor).not.toContainText('alert(1)');

    await page.goto('/admin/pages/create');
    await expect(page.getByRole('button', { name: 'Code source', exact: true })).toBeVisible();
  });

  test('shows existing V2 editorial images in the Quick halal page editor', async ({ page }) => {
    await page.goto('/admin');
    await page.locator('input[type="email"]').fill(email!);
    await page.locator('input[type="password"]').fill(password!);
    await page.locator('button[type="submit"]').click();
    await expect(page).toHaveURL(/\/admin$/);

    await page.goto('/admin/pages/11/edit');
    const image = page.locator('.editorial-rich-editor .tiptap img').first();

    await expect(image).toBeVisible();
    await expect(image).toHaveAttribute('src', /\/media\/\d+\/v\/[a-f0-9]{64}/);
    await expect(image).toHaveJSProperty('complete', true);
    expect(await image.evaluate((element: HTMLImageElement) => element.naturalWidth)).toBeGreaterThan(0);
  });
});
