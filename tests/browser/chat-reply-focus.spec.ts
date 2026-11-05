import { expect, test } from '@playwright/test';
import { collectFormErrors } from './support/forms';
import { box, carFor, chatsOf, insertMember, lines, removeChatLooks, signInMember, startChat } from './support/chat';

// The staff reply box takes focus (#38, `autofocus`): staff open a chat to answer it. Tessera reads `autofocus` as the
// server's choice of focus after an update, and a browser reads it on a whole page with no script. The visitor's box,
// in a dock on every store page, never carries it.

test.beforeEach(() => removeChatLooks());
test.afterEach(() => removeChatLooks());

test("the member's reply box takes focus on an open chat, and the visitor's box carries no autofocus", async ({ browser, page }) => {
    test.setTimeout(90_000);
    const errors = collectFormErrors(page);
    const ann = insertMember('Focus Ann');
    const car = carFor(ann);

    // The visitor starts a chat in a browser of their own: the dock's box carries no autofocus.
    const visitorContext = await browser.newContext();
    const visitor = await visitorContext.newPage();
    const visitorErrors = collectFormErrors(visitor);
    await startChat(visitor, car, { name: 'Chat Look Focus', email: '', message: 'Is it still for sale?' });
    await expect(box(visitor)).not.toHaveAttribute('autofocus', /.*/);
    const [chat] = chatsOf('Chat Look Focus');

    // Ann opens the chat from the list, a Tessera visit: the reply box carries autofocus and has the focus.
    await signInMember(page, ann);
    await page.goto('/app/chats');
    await page.locator(`#chat-row-${chat.id} a`).click();
    await expect(page).toHaveURL(new RegExp(`/app/chats/${chat.id}$`));
    await expect(lines(page)).toHaveText(['Is it still for sale?']);
    await expect(box(page)).toHaveAttribute('autofocus', '');
    await expect(box(page)).toBeFocused();

    // Her reply's answer redraws the form and patches `chatSent` at `@chat-send`: the box empties and keeps the focus.
    await box(page).fill('It is.');
    await box(page).press('Enter');
    await expect(lines(page)).toHaveText(['Is it still for sale?', 'It is.']);
    await expect(box(page)).toHaveValue('');
    await expect(box(page)).toBeFocused();

    // Opened whole, as a link from elsewhere would open it, the box has the focus too.
    await page.goto(`/app/chats/${chat.id}`);
    await expect(box(page)).toBeFocused();

    expect(errors).toEqual([]);
    expect(visitorErrors).toEqual([]);
    await visitorContext.close();
});

test('with no script, the browser gives the open chat\'s reply box the focus', async ({ browser }) => {
    test.setTimeout(90_000);
    const ann = insertMember('Plain Focus Ann');
    const car = carFor(ann);
    const visitorContext = await browser.newContext();
    const visitor = await visitorContext.newPage();
    await startChat(visitor, car, { name: 'Chat Look Plain Focus', email: '', message: 'Hello there' });
    const [chat] = chatsOf('Chat Look Plain Focus');

    const annContext = await browser.newContext({ javaScriptEnabled: false });
    const annPage = await annContext.newPage();
    await signInMember(annPage, ann);
    await annPage.goto(`/app/chats/${chat.id}`);
    await expect(lines(annPage)).toHaveText(['Hello there']);
    await expect(box(annPage)).toHaveAttribute('autofocus', '');
    await expect(box(annPage)).toBeFocused();

    await annContext.close();
    await visitorContext.close();
});
