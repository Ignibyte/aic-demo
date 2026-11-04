import { expect, test } from '@playwright/test';
import { collectFormErrors, pathOf } from './support/forms';
import { literal, sql } from './support/inventory';
import { runWorker } from './support/leads';
import {
    EVENTS,
    carFor,
    carWithoutLeads,
    chatTitle,
    chatsOf,
    dock,
    insertMember,
    lines,
    lookEmail,
    messagesOf,
    removeChatLooks,
    salesManager,
    startChat,
    startDock,
} from './support/chat';

test.beforeEach(() => removeChatLooks());
test.afterEach(() => removeChatLooks());

// These looks run the worker on this host (`$AIC_PHP bin/aic queue:work`, AIC #225), as the leads looks do.
test("a car's page offers the chat; a start opens the dock in place, and the chat goes to the car's salesperson, who is told", async ({ page }) => {
    const errors = collectFormErrors(page);
    const ann = insertMember('Chat Ann');
    const car = carFor(ann);
    const title = chatTitle(car);

    // Only a car's page offers a start: the list and /chat hold no dock while no chat is open.
    await page.goto('/cars');
    await expect(page.locator('#chat-start, #chat-dock')).toHaveCount(0);
    await page.goto('/chat');
    await expect(page.locator('#chat-heading')).toHaveText('No chat open');
    await expect(page.locator('#chat-start, #chat-dock')).toHaveCount(0);
    await page.goto(car.path);
    await expect(startDock(page).locator('summary')).toHaveText('Chat with us about this car');
    await expect(startDock(page)).not.toHaveAttribute('open', /.*/);
    await expect(startDock(page)).not.toHaveAttribute('data-permanent', /.*/);
    await expect(startDock(page).locator('form')).toHaveAttribute('action', `${car.path}/chat`);

    // One POST, answered in place: the same document, the dock open on the chat, its stream connected.
    await page.evaluate(() => {
        (window as unknown as { chatLook: string }).chatLook = 'same document';
    });
    const posts: string[] = [];
    const streams: string[] = [];
    page.on('request', (request) => {
        if (request.method() === 'POST') {
            posts.push(pathOf(request.url()));
        }

        if (pathOf(request.url()) === EVENTS) {
            streams.push(request.url());
        }
    });
    const email = lookEmail('visitor');
    await startDock(page).locator('summary').click();
    await page.locator('#chat-name').fill('Chat Look Start');
    await page.locator('#chat-email').fill(email);
    await page.locator('#chat-message').fill('Is it still for sale?');
    await page.locator('#chat-start-go').click();
    await expect(dock(page)).toBeVisible();
    await expect(dock(page)).toHaveAttribute('data-permanent', '');
    await expect(dock(page)).toHaveAttribute('data-stream', EVENTS);
    await expect(dock(page)).toHaveAttribute('open', '');
    await expect(dock(page).locator('summary')).toContainText(`Chat about the ${title}`);
    await expect(lines(page)).toHaveText(['Is it still for sale?']);
    await expect(page.locator('#chat-start')).toHaveCount(0);
    // The start is the one write; opening the dock posts the read beside it.
    expect(posts.filter((path) => path !== '/chat/read')).toEqual([`${car.path}/chat`]);
    expect(await page.evaluate(() => (window as unknown as { chatLook?: string }).chatLook)).toBe('same document');
    await expect.poll(() => streams.length).toBeGreaterThan(0);

    // Postgres holds the chat, gone to Ann, and its first message as seq 1.
    const [chat] = chatsOf('Chat Look Start');
    expect(chat).toMatchObject({ staffId: ann.id, status: 'open', lastSeq: 1, visitorSeen: 1, staffSeen: 0, email, carTitle: title });
    expect(messagesOf(chat.id)).toEqual(['1|visitor|Is it still for sale?']);

    // One worker run leaves Ann the notification, linking to the chat.
    expect(runWorker()).toMatch(/queue:work ran \d+ job\(s\)\./);
    const notices = sql(`SELECT subject || '|' || link FROM aic_notifications WHERE user_id = ${literal(ann.id)}`);
    expect(notices).toEqual([`New chat about the ${title}|/app/chats/${chat.id}`]);
    expect(errors).toEqual([]);
});

test('a chat on a car with no lead goes to the active sales manager with the lowest id', async ({ page }) => {
    const errors = collectFormErrors(page);
    const car = carWithoutLeads();
    const manager = salesManager();
    await startChat(page, car, { name: 'Chat Look Manager', email: '', message: 'Who do I talk to?' });
    await expect(lines(page)).toHaveText(['Who do I talk to?']);
    const [chat] = chatsOf('Chat Look Manager');
    expect(chat.staffId).toBe(manager);
    expect(chat.email).toBe('');
    expect(errors).toEqual([]);
});
