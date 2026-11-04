import { expect, test } from '@playwright/test';
import { collectFormErrors } from './support/forms';
import { mailFor, mailText, runWorker } from './support/leads';
import {
    EVENTS,
    box,
    carFor,
    chatTitle,
    chatsOf,
    dock,
    insertMember,
    lines,
    lookEmail,
    removeChatLooks,
    signInMember,
    startChat,
    startDock,
    transcriptJobs,
} from './support/chat';

test.beforeEach(() => removeChatLooks());
test.afterEach(() => removeChatLooks());

// These looks run the worker on this host and read DDEV's Mailpit, as the leads looks do.
test("the member's close ends the chat on the visitor's dock; a chat with an email has its transcript mailed", async ({ browser, page }) => {
    test.setTimeout(60_000);
    const errors = collectFormErrors(page);
    const ann = insertMember('End Ann');
    const car = carFor(ann);
    const title = chatTitle(car);
    const email = lookEmail('transcript');
    await startChat(page, car, { name: 'Chat Look End', email, message: 'Can I see it?' });
    const [chat] = chatsOf('Chat Look End');

    // Ann replies, then closes it from the conversation, landing on the list with the notice.
    const annContext = await browser.newContext();
    const annPage = await annContext.newPage();
    const annErrors = collectFormErrors(annPage);
    await signInMember(annPage, ann);
    await annPage.goto(`/app/chats/${chat.id}`);
    await box(annPage).fill('Yes, come at four.');
    await box(annPage).press('Enter');
    await expect(lines(page)).toHaveText(['Can I see it?', 'Yes, come at four.'], { timeout: 10_000 });
    await page.evaluate((events) => {
        const seen: string[] = [];
        (window as unknown as { chatExchanges: string[] }).chatExchanges = seen;
        document.addEventListener('tessera:exchange', (event) => {
            const detail = (event as CustomEvent<{ kind: string; outcome: string; url: string }>).detail;

            if (new URL(detail.url).pathname === events) {
                seen.push(`${detail.kind}:${detail.outcome}`);
            }
        });
    }, EVENTS);
    await annPage.locator('#chat-close-go').click();
    await expect(annPage).toHaveURL(/\/app\/chats$/);
    await expect(annPage.locator('#chats-notice')).toHaveText('Closed the chat with Chat Look End.');
    await expect(annPage.locator(`#chat-row-${chat.id}`)).toContainText('Ended');

    // The visitor's dock says so within a look, in place of the send form, and its stream ends.
    await expect(page.locator('#chat-ended')).toHaveText('This chat has ended.', { timeout: 10_000 });
    await expect(box(page)).toHaveCount(0);
    // The connection that sent the notice ends with it: Tessera reports its `tessera-close`, and opens no other.
    const exchanges = async (): Promise<string[]> => page.evaluate(() => (window as unknown as { chatExchanges: string[] }).chatExchanges);
    await expect.poll(exchanges).toContain('stream-event:closed');
    await page.waitForTimeout(3_000);
    expect((await exchanges()).filter((exchange) => exchange.startsWith('stream:'))).toEqual([]);
    expect(chatsOf('Chat Look End')[0].status).toBe('ended');

    // One transcript job; one worker run mails every message, oldest first, signed by the dealership.
    expect(transcriptJobs(chat.id)).toBe(1);
    expect(runWorker()).toMatch(/queue:work ran \d+ job\(s\)\./);
    expect(runWorker()).toMatch(/queue:work ran \d+ job\(s\)\./);
    const mails = await mailFor(email);
    expect(mails.map((mail) => mail.subject)).toEqual([`Your chat about the ${title}`]);
    const text = await mailText(mails[0]);
    expect(text).toMatch(/\d{4}-\d\d-\d\d \d\d:\d\d UTC Chat Look End: Can I see it\?\r?\n\d{4}-\d\d-\d\d \d\d:\d\d UTC End Ann: Yes, come at four\./);
    expect(transcriptJobs(chat.id)).toBe(0);

    // A reload shows no dock: the chat is not open any more; the car's page offers a start again.
    await page.reload();
    await expect(dock(page)).toHaveCount(0);
    await expect(startDock(page)).toBeVisible();
    await annContext.close();
    expect(errors).toEqual([]);
    expect(annErrors).toEqual([]);
});

test("the visitor's End chat removes the dock; a chat with no email queues no transcript", async ({ page }) => {
    const errors = collectFormErrors(page);
    const ann = insertMember('End Bo');
    const car = carFor(ann);
    await startChat(page, car, { name: 'Chat Look Quiet', email: '', message: 'Just looking' });
    const [chat] = chatsOf('Chat Look Quiet');
    await page.locator('#chat-end-go').click();
    await expect(dock(page)).toHaveCount(0);
    await expect(startDock(page)).toBeVisible();
    expect(chatsOf('Chat Look Quiet')[0].status).toBe('ended');
    expect(transcriptJobs(chat.id)).toBe(0);

    // The chat page says none is open.
    await page.goto('/chat');
    await expect(page.locator('#chat-heading')).toHaveText('No chat open');
    expect(errors).toEqual([]);
});
