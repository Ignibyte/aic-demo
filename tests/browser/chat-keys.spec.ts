import { expect, test } from '@playwright/test';
import { collectFormErrors, pathOf } from './support/forms';
import { literal, sql } from './support/inventory';
import { box, carFor, chatsOf, insertMember, lines, messagesOf, removeChatLooks, startChat } from './support/chat';

test.beforeEach(() => removeChatLooks());
test.afterEach(() => removeChatLooks());

test('Enter sends and the box clears; Shift+Enter breaks the line; a refusal stays in the box; typing survives a patch', async ({ page }) => {
    const errors = collectFormErrors(page);
    const ann = insertMember('Keys Ann');
    const car = carFor(ann);
    await startChat(page, car, { name: 'Chat Look Keys', email: '', message: 'First' });
    const [chat] = chatsOf('Chat Look Keys');
    const sends: string[] = [];
    page.on('request', (request) => {
        if (request.method() === 'POST' && pathOf(request.url()) === '/chat/messages') {
            sends.push(request.postData() ?? '');
        }
    });

    // Shift+Enter puts a line break in the box and sends nothing; Enter sends once and the box clears on the answer.
    await box(page).click();
    await page.keyboard.type('Two lines');
    await page.keyboard.press('Shift+Enter');
    await page.keyboard.type('the second');
    await expect(box(page)).toHaveValue('Two lines\nthe second');
    expect(sends).toEqual([]);
    await page.keyboard.press('Enter');
    await expect(lines(page)).toHaveText(['First', 'Two lines\nthe second']);
    await expect(box(page)).toHaveValue('');
    expect(sends).toHaveLength(1);
    expect(new URLSearchParams(sends[0]).get('message')).toBe('Two lines\nthe second');
    expect(messagesOf(chat.id)).toEqual(['1|visitor|First', '2|visitor|Two lines\\nthe second']);

    // The line break shows in the thread, which keeps the text's lines.
    await expect(lines(page).nth(1)).toHaveCSS('white-space', 'pre-line');

    // A message over 1,000 characters is refused in place: the reason shows and takes focus, and the text stays.
    const long = 'x'.repeat(1001);
    await box(page).fill(long);
    await box(page).press('Enter');
    await expect(page.locator('#chat-send-form-reason')).toHaveText('Keep a message to 1,000 characters.');
    await expect(page.locator('#chat-send-form-reason')).toBeFocused();
    await expect(box(page)).toHaveValue(long);
    expect(messagesOf(chat.id)).toHaveLength(2);

    // Text typed in the box stays while a member's message lands through the stream.
    await box(page).fill('Half typed');
    sql(
        `INSERT INTO chat_messages (chat_id, seq, side, staff_id, body, created_at) VALUES (${chat.id}, 3, 'staff', ${literal(ann.id)}, 'Hi from Ann', now())`,
    );
    sql(`UPDATE chats SET last_seq = 3 WHERE id = ${chat.id}`);
    await expect(lines(page)).toHaveText(['First', 'Two lines\nthe second', 'Hi from Ann'], { timeout: 10_000 });
    await expect(box(page)).toHaveValue('Half typed');
    expect(errors).toEqual([]);
});
