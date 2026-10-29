import { expect, test } from '@playwright/test';
import { collectFormErrors } from './support/forms';
import { fetchInPage } from './support/photos';
import { insertMember, insertNotification, isRead, removeInboxLooks, signInMember, unreadOf } from './support/inbox';

test.beforeEach(() => removeInboxLooks());
test.afterEach(() => removeInboxLooks());

test("another member's notification is not found, never shown, and stays unread", async ({ page }) => {
    const errors = collectFormErrors(page);
    const member = insertMember('own');
    const other = insertMember('other');
    insertNotification(member, 'Own look notification', 1);
    const theirs = insertNotification(other, 'Other look notification', 0);
    await signInMember(page, member);

    // Its id, forged into the opening, answers 404 and changes nothing.
    const forged = await fetchInPage(page, `/app/inbox/${theirs}`);
    expect(forged.status).toBe(404);
    expect(isRead(theirs)).toBe(false);

    // It is in neither the member's bell nor their inbox.
    await expect(page.locator('#bell-items')).not.toContainText('Other look notification');
    await expect(page.locator(`#bell-item-${theirs}`)).toHaveCount(0);
    await page.goto('/app/inbox');
    await expect(page.locator('#inbox-table tbody tr')).toHaveCount(1);
    await expect(page.locator('#inbox-table')).toContainText('Own look notification');
    await expect(page.locator('#inbox-table')).not.toContainText('Other look notification');

    // The member's "Mark all read" marks theirs alone.
    await page.locator('#inbox-read-all').click();
    await expect(page.locator('#inbox-notice')).toContainText('Marked every notification read.');
    expect(unreadOf(member)).toBe(0);
    expect(unreadOf(other)).toBe(1);
    expect(isRead(theirs)).toBe(false);

    // Chrome logs the forged opening's 404, which this look asked for; nothing else may log an error.
    const asked = 'Failed to load resource: the server responded with a status of 404';
    expect(errors.filter((error) => !error.startsWith(asked))).toEqual([]);
});
