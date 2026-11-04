import { expect, test } from '@playwright/test';
import { collectFormErrors } from './support/forms';
import { box, carFor, chatsOf, dock, insertMember, lines, removeChatLooks, signInMember, startChat } from './support/chat';

test.beforeEach(() => removeChatLooks());
test.afterEach(() => removeChatLooks());

test('messages land on both sides with no reload; typing shows and clears; unread follows on the dock and on the list', async ({ browser, page }) => {
    test.setTimeout(120_000);
    const errors = collectFormErrors(page);
    const ann = insertMember('Live Ann');
    const car = carFor(ann);

    // The visitor starts the chat in a browser of their own.
    const visitorContext = await browser.newContext();
    const visitor = await visitorContext.newPage();
    const visitorErrors = collectFormErrors(visitor);
    await startChat(visitor, car, { name: 'Chat Look Live', email: '', message: 'Hello?' });
    const [chat] = chatsOf('Chat Look Live');

    // Ann sees it in the list with one unread, then opens it, in a document the look marks.
    await signInMember(page, ann);
    await page.goto('/app/chats');
    await expect(page.locator(`#chat-unread-${chat.id}`)).toHaveText('1');
    await page.locator(`#chat-row-${chat.id} a`).click();
    await expect(page).toHaveURL(new RegExp(`/app/chats/${chat.id}$`));
    await expect(lines(page)).toHaveText(['Hello?']);
    await page.evaluate(() => {
        (window as unknown as { chatLook: string }).chatLook = 'same document';
    });

    // The visitor types: Ann sees it; it clears once their message lands, which lands on Ann's page with no reload.
    await box(visitor).pressSequentially('Are you there');
    await expect(page.locator('#chat-typing')).toBeVisible({ timeout: 10_000 });
    await expect(page.locator('#chat-typing')).toHaveText('Chat Look Live is typing…');
    await box(visitor).press('Enter');
    await expect(lines(page)).toHaveText(['Hello?', 'Are you there'], { timeout: 10_000 });
    await expect(page.locator('#chat-typing')).toBeHidden({ timeout: 10_000 });

    // Ann types: the visitor sees the team typing, and it clears after five seconds with no more typing.
    await box(page).pressSequentially('One mo');
    await expect(visitor.locator('#chat-typing')).toBeVisible({ timeout: 10_000 });
    await expect(visitor.locator('#chat-typing')).toHaveText('Our team is typing…');
    await expect(visitor.locator('#chat-typing')).toBeHidden({ timeout: 12_000 });

    // With the dock closed, Ann's reply lands and raises the unread badge; opening the dock reads it and the badge goes.
    await dock(visitor).locator('summary').click();
    await expect(dock(visitor)).not.toHaveAttribute('open', /.*/);
    await expect(visitor.locator('#chat-unread')).toBeHidden();
    await box(page).fill('Yes, it is here.');
    await box(page).press('Enter');
    await expect(lines(page)).toHaveText(['Hello?', 'Are you there', 'Yes, it is here.']);
    await expect(visitor.locator('#chat-unread')).toHaveText('1', { timeout: 10_000 });
    await expect(visitor.locator('#chat-unread')).toBeVisible();
    await dock(visitor).locator('summary').click();
    await expect(lines(visitor)).toHaveText(['Hello?', 'Are you there', 'Yes, it is here.']);
    await expect(visitor.locator('#chat-unread')).toBeHidden({ timeout: 10_000 });
    expect(await page.evaluate(() => (window as unknown as { chatLook?: string }).chatLook)).toBe('same document');

    // Back on the list, the visitor's next message raises its unread count by the list's poll, with no reload. The
    // conversation's stream marks what it sends seen, and the server learns its client has gone only at a write that
    // fails, which DDEV's router in front of nginx can hold back; a connection lasts at most twenty one-second looks, so
    // the look waits that long first.
    await page.goto('/app/chats');
    await expect(page.locator(`#chat-unread-${chat.id}`)).toHaveText('0');
    await page.waitForTimeout(21_000);
    await page.evaluate(() => {
        (window as unknown as { chatLook: string }).chatLook = 'list document';
    });
    await box(visitor).fill('Can I come at five?');
    await box(visitor).press('Enter');
    // Tessera fires a poll on every tick only while its document has the focus; the visitor's typing took it.
    await page.bringToFront();
    await expect(page.locator(`#chat-unread-${chat.id}`)).toHaveText('1', { timeout: 15_000 });
    expect(await page.evaluate(() => (window as unknown as { chatLook?: string }).chatLook)).toBe('list document');
    await visitorContext.close();
    expect(errors).toEqual([]);
    expect(visitorErrors).toEqual([]);
});
