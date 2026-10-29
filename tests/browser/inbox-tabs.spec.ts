import { expect, test } from '@playwright/test';
import { collectFormErrors } from './support/forms';
import { recordPolls } from './support/live';
import { expectBellCount, insertMember, insertNotification, isRead, removeInboxLooks, signInMember } from './support/inbox';

test.beforeEach(() => removeInboxLooks());
test.afterEach(() => removeInboxLooks());

test("a notification read in one tab drops the dashboard's bell in another, and the dashboard keeps polling", async ({ page }) => {
    const errors = collectFormErrors(page);
    const member = insertMember('tabs');
    const id = insertNotification(member, 'Tabs look notification', 0);
    await signInMember(page, member);
    await expectBellCount(page, 1);
    const polls = await recordPolls(page);

    // Another tab opens the notification, which has no link, so it lands on the inbox.
    const other = await page.context().newPage();
    await other.goto(`/app/inbox/${id}`);
    await expect(other).toHaveURL(/\/app\/inbox$/);
    expect(isRead(id)).toBe(true);
    await other.close();

    // The dashboard's bell drops on the stream's next event, and its poll goes on beside the stream.
    await expectBellCount(page, 0);
    await expect.poll(() => polls.filter((poll) => poll.tesseraRequest === 'refresh' && poll.finished).length, { timeout: 15_000 })
        .toBeGreaterThan(0);
    expect(polls.filter((poll) => poll.tesseraRequest === 'refresh').every((poll) => [200, 204].includes(poll.status))).toBe(true);
    expect(polls.filter((poll) => poll.tesseraRequest === 'stream')).toEqual([]);
    expect(errors).toEqual([]);
});
