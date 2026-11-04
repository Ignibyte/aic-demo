import { expect, test } from '@playwright/test';
import { carFor, chatsOf, dock, insertMember, lines, messagesOf, removeChatLooks, signInMember, startDock } from './support/chat';

test.beforeEach(() => removeChatLooks());
test.afterEach(() => removeChatLooks());

test.use({ javaScriptEnabled: false });

test('with JavaScript off every part of the chat is a form that posts and reloads', async ({ browser, page }) => {
    const ann = insertMember('Plain Ann');
    const car = carFor(ann);

    // The start form posts; the car's page comes back with the dock open on the chat.
    await page.goto(car.path);
    await startDock(page).locator('summary').click();
    await page.locator('#chat-name').fill('Chat Look Plain');
    await page.locator('#chat-message').fill('No script here');
    await page.locator('#chat-start-go').press('Enter');
    await expect(page).toHaveURL(new RegExp(`${car.path}/chat$`));
    await expect(dock(page)).toHaveAttribute('open', '');
    await expect(lines(page)).toHaveText(['No script here']);
    const [chat] = chatsOf('Chat Look Plain');

    // The car's page itself shows the dock closed, a <details> the browser opens.
    await page.goto(car.path);
    await expect(dock(page)).not.toHaveAttribute('open', /.*/);
    await dock(page).locator('summary').click();
    await expect(dock(page)).toHaveAttribute('open', '');

    // The send form posts, and the page it came from reloads with the message in the thread.
    await page.locator('#chat-send-form-message').fill('Still no script');
    await page.locator('#chat-send-form-go').press('Enter');
    await expect(page).toHaveURL(new RegExp(`${car.path}$`));
    await dock(page).locator('summary').click();
    await expect(lines(page)).toHaveText(['No script here', 'Still no script']);

    // Ann, with no script either, replies and closes from the conversation; each posts and reloads.
    const annContext = await browser.newContext({ javaScriptEnabled: false });
    const annPage = await annContext.newPage();
    await signInMember(annPage, ann);
    await annPage.goto(`/app/chats/${chat.id}`);
    await expect(lines(annPage)).toHaveText(['No script here', 'Still no script']);
    await annPage.locator('#chat-send-form-message').fill('Hello from Ann');
    await annPage.locator('#chat-send-form-go').press('Enter');
    await expect(annPage).toHaveURL(new RegExp(`/app/chats/${chat.id}$`));
    await expect(lines(annPage)).toHaveText(['No script here', 'Still no script', 'Hello from Ann']);
    expect(messagesOf(chat.id)).toHaveLength(3);

    // The visitor ends the chat: the page reloads with no dock and the start offered again.
    await page.reload();
    await dock(page).locator('summary').click();
    await expect(lines(page)).toHaveText(['No script here', 'Still no script', 'Hello from Ann']);
    await page.locator('#chat-end-go').press('Enter');
    await expect(page).toHaveURL(new RegExp(`${car.path}$`));
    await expect(dock(page)).toHaveCount(0);
    await expect(startDock(page)).toBeVisible();
    expect(chatsOf('Chat Look Plain')[0].status).toBe('ended');

    // Ann's conversation shows it ended, with no close form.
    await annPage.goto(`/app/chats/${chat.id}`);
    await expect(annPage.locator('#chat-status')).toHaveText('Ended');
    await expect(annPage.locator('#chat-close-go')).toHaveCount(0);
    await annContext.close();
});

test('with JavaScript off the member closes a chat and lands on the list with the notice', async ({ browser, page }) => {
    const ann = insertMember('Plain Bo');
    const car = carFor(ann);
    await page.goto(car.path);
    await startDock(page).locator('summary').click();
    await page.locator('#chat-name').fill('Chat Look Closed');
    await page.locator('#chat-message').fill('Close me');
    await page.locator('#chat-start-go').press('Enter');
    const [chat] = chatsOf('Chat Look Closed');
    const annContext = await browser.newContext({ javaScriptEnabled: false });
    const annPage = await annContext.newPage();
    await signInMember(annPage, ann);
    await annPage.goto(`/app/chats/${chat.id}`);
    await annPage.locator('#chat-close-go').press('Enter');
    await expect(annPage).toHaveURL(/\/app\/chats$/);
    await expect(annPage.locator('#chats-notice')).toHaveText('Closed the chat with Chat Look Closed.');
    await annContext.close();
});
