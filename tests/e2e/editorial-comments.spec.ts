import { expect, test } from '@playwright/test';

test('Quick halal exposes crawlable threaded comments', async ({ page }) => {
    const errors: string[] = [];
    page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
    page.on('requestfailed', request => {
        if (new URL(request.url()).origin === new URL(page.url()).origin) errors.push(request.url());
    });

    await page.goto('/quick-hallal');
    const threads = page.locator('#comment-threads > .comment:not(.comment-reply)');
    await expect(threads).toHaveCount(20);
    const more = page.getByRole('link', { name: /Afficher 20 commentaires de plus/ });
    await expect(more).toHaveAttribute('href', /comments_page=2/);
    await expect(page.locator('link[rel="canonical"]')).toHaveAttribute('href', /\/quick-hallal$/);

    const pageTwo = await page.request.get('/quick-hallal?comments_page=2');
    expect(pageTwo.status()).toBe(200);
    expect(await pageTwo.text()).toContain('comments_page=3');
    expect(await pageTwo.text()).toContain(`rel="canonical" href="${page.url()}"`);

    await more.click();
    await expect(threads).toHaveCount(40);
    await expect(page.getByRole('link', { name: /Afficher 20 commentaires de plus/ })).toHaveAttribute('href', /comments_page=3/);
    await page.locator('[data-reply-details] summary').first().click();
    await expect(page.getByText(/Répondre à/).first()).toBeVisible();
    expect(errors).toEqual([]);
});
