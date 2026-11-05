import { expect, test } from '@playwright/test';
import { collectFormErrors, pathOf } from './support/forms';
import { literal, sql } from './support/inventory';
import { EVENTS, carFor, chatsOf, dock, insertMember, lines, readStream, removeChatLooks, startChat } from './support/chat';

test.beforeEach(() => removeChatLooks());
test.afterEach(() => removeChatLooks());

// Adds a member's message to the chat as the app would: the next seq, `last_seq` raised.
function reply(chatId: number, memberId: string, text: string): void {
    sql(
        'INSERT INTO chat_messages (chat_id, seq, side, staff_id, body, created_at) '
            + `SELECT ${chatId}, last_seq + 1, 'staff', ${literal(memberId)}, ${literal(text)}, now() FROM chats WHERE id = ${chatId}`,
    );
    sql(`UPDATE chats SET last_seq = last_seq + 1 WHERE id = ${chatId}`);
}

test('a fresh read sends the thread then the signals; a resumed one only later messages; a dropped connection loses none', async ({ page }) => {
    test.setTimeout(90_000);
    const errors = collectFormErrors(page);
    const ann = insertMember('Resume Ann');
    const car = carFor(ann);
    await startChat(page, car, { name: 'Chat Look Resume', email: '', message: 'One' });
    const [chat] = chatsOf('Chat Look Resume');

    // The open dock posts the visitor's read when its own stream brings a message, so it would see "Two" whenever it
    // got there before the reads below. Closed, it posts nothing, and the seen mark stays at 1.
    await expect(dock(page)).toHaveAttribute('open', '');
    await page.locator('#chat-dock-summary').click();
    await expect(dock(page)).not.toHaveAttribute('open');
    reply(chat.id, ann.id, 'Two');

    // Fresh: the whole thread as the log's inner markup, its id the newest seq, then the signals with no id.
    const fresh = await readStream(page, EVENTS, '', 2);
    expect(fresh.map((event) => [event.event, event.target, event.mode, event.id])).toEqual([
        ['tessera-patch-elements', '@chat-messages', 'inner', '2'],
        ['tessera-patch-signals', '@chat', 'morph', ''],
    ]);
    expect(fresh[0].elements).toContain('>One</div>');
    expect(fresh[0].elements).toContain('>Two</div>');
    expect(JSON.parse(fresh[1].signals)).toEqual({ chatTyping: false, chatUnread: 1 });
    expect(chatsOf('Chat Look Resume')[0].visitorSeen).toBe(1);

    // Resumed from seq 1: the later message alone, as an append with its seq; from the newest, no message.
    const resumed = await readStream(page, EVENTS, '1', 2);
    expect(resumed.map((event) => [event.target, event.mode, event.id])).toEqual([
        ['@chat-messages', 'append', '2'],
        ['@chat', 'morph', ''],
    ]);
    expect(resumed[0].elements).toContain('>Two</div>');
    expect(resumed[0].elements).not.toContain('>One</div>');
    // From the newest, signals alone (a second when the signals change meanwhile), no message.
    const newest = await readStream(page, EVENTS, '2', 2);
    expect(newest.length).toBeGreaterThan(0);
    expect(newest.every((event) => event.event === 'tessera-patch-signals' && event.target === '@chat')).toBe(true);

    // The page's own connection drops while Ann sends two messages; Tessera reconnects with its last id, and each
    // lands once, in order. The dock is open again for it, as a visitor reading the chat has it.
    await page.locator('#chat-dock-summary').click();
    await expect(dock(page)).toHaveAttribute('open', '');
    await expect(lines(page)).toHaveText(['One', 'Two'], { timeout: 10_000 });
    let dropped = false;
    await page.route((url) => url.pathname === EVENTS, async (route) => {
        if (!dropped) {
            dropped = true;
            await route.abort('connectionreset');

            return;
        }

        await route.continue();
    });
    const resumes: string[] = [];
    page.on('request', (request) => {
        if (pathOf(request.url()) === EVENTS) {
            resumes.push(request.headers()['last-event-id'] ?? '');
        }
    });
    reply(chat.id, ann.id, 'Three');
    reply(chat.id, ann.id, 'Four');
    await expect(lines(page)).toHaveText(['One', 'Two', 'Three', 'Four'], { timeout: 40_000 });
    expect(resumes.every((id) => id !== '')).toBe(true);
    expect(errors.filter((error) => !error.includes('ERR_CONNECTION_RESET'))).toEqual([]);
});

test('the visitor\'s stream pauses while the tab is hidden and resumes from its last id when it is visible', async ({ page }) => {
    test.setTimeout(60_000);
    const errors = collectFormErrors(page);
    const ann = insertMember('Pause Ann');
    const car = carFor(ann);
    const opened: string[] = [];
    page.on('response', (response) => {
        if (pathOf(response.url()) === EVENTS) {
            opened.push(response.headers()['tessera-stream-pause'] ?? '');
        }
    });
    await startChat(page, car, { name: 'Chat Look Pause', email: '', message: 'Pause me' });
    const [chat] = chatsOf('Chat Look Pause');
    await expect.poll(() => opened.length).toBe(1);
    expect(opened).toEqual(['hidden']);

    // The tab goes hidden: Tessera closes the connection. A message sent meanwhile lands once the tab is visible again,
    // on a new connection that names the last id it saw.
    const lastIds: string[] = [];
    page.on('request', (request) => {
        if (pathOf(request.url()) === EVENTS) {
            lastIds.push(request.headers()['last-event-id'] ?? '');
        }
    });
    const setVisibility = async (state: 'hidden' | 'visible'): Promise<void> => {
        await page.evaluate((value) => {
            Object.defineProperty(document, 'visibilityState', { configurable: true, get: () => value });
            Object.defineProperty(document, 'hidden', { configurable: true, get: () => value === 'hidden' });
            document.dispatchEvent(new Event('visibilitychange'));
        }, state);
    };
    await setVisibility('hidden');
    reply(chat.id, ann.id, 'While hidden');
    await page.waitForTimeout(3_000);
    await expect(lines(page)).toHaveText(['Pause me']);
    expect(lastIds).toEqual([]);
    await setVisibility('visible');
    await expect(lines(page)).toHaveText(['Pause me', 'While hidden'], { timeout: 10_000 });
    expect(lastIds[0]).toBe('1');
    expect(errors).toEqual([]);
});
