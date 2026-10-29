import { expect, test } from '@playwright/test';
import { collectFormErrors } from './support/forms';
import { expectBellCount, insertMember, insertNotification, removeInboxLooks, signInMember, unreadOf } from './support/inbox';

test.beforeEach(() => removeInboxLooks());
test.afterEach(() => removeInboxLooks());

test('the inbox lists twenty a page newest first, each linking to its opening, and "Mark all read" marks them all', async ({ page }) => {
    const errors = collectFormErrors(page);
    const member = insertMember('list');
    const ids: number[] = [];

    for (let n = 1; n <= 23; n++) {
        ids.push(insertNotification(member, `List look ${n}`, 23 - n));
    }

    const newestFirst = [...ids].reverse();
    await signInMember(page, member);
    await expectBellCount(page, 23);
    await page.locator('#bell').click();
    await expect(page.locator('#bell-items > li:visible')).toHaveCount(5);
    await expect(page.locator('#bell-items > li:visible').first()).toContainText('List look 23');

    // The first page: the newest twenty.
    await page.locator('#bell-all').click();
    await expect(page).toHaveURL(/\/app\/inbox$/);
    await expect(page.locator('#inbox-table-summary')).toHaveText('23 notifications, 23 unread, newest first (page 1 of 2)');
    const rows = page.locator('#inbox-table tbody tr');
    await expect(rows).toHaveCount(20);
    await expect(rows.first()).toContainText('List look 23');
    await expect(rows.last()).toContainText('List look 4');
    const firstPage = await rows.locator('a').evaluateAll((links) => links.map((link) => link.getAttribute('href')));
    expect(firstPage).toEqual(newestFirst.slice(0, 20).map((id) => `/app/inbox/${id}`));

    // The second page: the last three.
    await page.locator('#inbox-pages').getByRole('link', { name: '2', exact: true }).click();
    await expect(page).toHaveURL(/\/app\/inbox\?page=2$/);
    await expect(rows).toHaveCount(3);
    const secondPage = await rows.locator('a').evaluateAll((links) => links.map((link) => link.getAttribute('href')));
    expect(secondPage).toEqual(newestFirst.slice(20).map((id) => `/app/inbox/${id}`));

    // "Mark all read" marks every one and says so; the bell goes dark.
    await page.locator('#inbox-read-all').click();
    await expect(page).toHaveURL(/\/app\/inbox$/);
    await expect(page.locator('#inbox-notice')).toContainText('Marked every notification read.');
    await expect(page.locator('#inbox-table-summary')).toHaveText('23 notifications, 0 unread, newest first (page 1 of 2)');
    await expect(page.locator('#inbox-read-all')).toHaveCount(0);
    expect(unreadOf(member)).toBe(0);
    await expectBellCount(page, 0);
    expect(errors).toEqual([]);
});
