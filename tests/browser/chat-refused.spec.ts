import { expect, test } from '@playwright/test';
import { collectFormErrors } from './support/forms';
import { sql } from './support/inventory';
import { carFor, chatsOf, insertMember, messagesOf, post, removeChatLooks, signInMember, startChat } from './support/chat';

test.beforeEach(() => removeChatLooks());
test.afterEach(() => removeChatLooks());

// Chrome's own lines for the statuses these looks ask for on purpose.
const OWN = /Failed to load resource: the server responded with a status of (400|403|404|409)/;

// How many chats the looks' visitors have, of any name.
function lookChats(): number {
    return Number(sql("SELECT count(*) FROM chats WHERE visitor_name LIKE 'Chat Look%'")[0]);
}

test('a start refused by the guard, its fields or the limit writes nothing; four starts in an hour are one too many', async ({ page }) => {
    const errors = collectFormErrors(page);
    const ann = insertMember('Refused Ann');
    const car = carFor(ann);
    await page.goto(car.path);
    const fields = { name: 'Chat Look Refused', email: '', message: 'Hello' };

    expect((await post(page, `${car.path}/chat`, fields, { token: '' })).status).toBe(403);
    expect((await post(page, `${car.path}/chat`, { ...fields, aic_website: 'spam' })).status).toBe(400);
    const empty = await post(page, `${car.path}/chat`, { ...fields, message: '   ' });
    expect(empty.status).toBe(422);
    expect(empty.body).toContain('Write a message before you send it.');
    const email = await post(page, `${car.path}/chat`, { ...fields, email: 'nope' });
    expect(email.status).toBe(422);
    expect(email.body).toContain('Give an email like you@example.com, or leave it empty.');
    expect(email.body).toContain('value="nope"');
    expect((await post(page, '/cars/2023-volkswagen-tiguan-000000/chat', fields)).status).toBe(404);
    expect(lookChats()).toBe(0);

    // Three starts go; the fourth within the hour is refused with its reason, and no fourth chat is written.
    for (let n = 1; n <= 3; n += 1) {
        expect((await post(page, `${car.path}/chat`, { ...fields, name: `Chat Look Start ${n}` })).status).toBe(200);
    }

    const fourth = await post(page, `${car.path}/chat`, { ...fields, name: 'Chat Look Start 4' });
    expect(fourth.status).toBe(422);
    expect(fourth.body).toContain('You have started three chats this hour. Wait a while, then start another.');
    expect(lookChats()).toBe(3);
    expect(errors.filter((error) => !OWN.test(error))).toEqual([]);
});

test('sends refused by the guard, the box or the limit write nothing; members see only their own chats; an ended chat takes none', async ({ browser, page }) => {
    const errors = collectFormErrors(page);
    const ann = insertMember('Refused Ann');
    const bo = insertMember('Refused Bo');
    const car = carFor(ann);
    await startChat(page, car, { name: 'Chat Look Sends', email: '', message: 'Message 1' });
    const [chat] = chatsOf('Chat Look Sends');

    // The visitor's sends: no token, empty, too long.
    expect((await post(page, '/chat/messages', { message: 'x', back: car.path }, { token: '' })).status).toBe(403);
    expect((await post(page, '/chat/messages', { message: 'x', back: car.path, aic_website: 'spam' })).status).toBe(400);
    expect((await post(page, '/chat/messages', { message: '  ', back: car.path })).status).toBe(422);
    expect((await post(page, '/chat/messages', { message: 'y'.repeat(1001), back: car.path })).status).toBe(422);
    expect(messagesOf(chat.id)).toHaveLength(1);

    // Ten visitor messages in a minute go, the start's among them; the eleventh is refused with its reason.
    for (let n = 2; n <= 10; n += 1) {
        expect((await post(page, '/chat/messages', { message: `Message ${n}`, back: car.path })).status).toBe(303);
    }

    const eleventh = await post(page, '/chat/messages', { message: 'Message 11', back: car.path });
    expect(eleventh.status).toBe(422);
    expect(eleventh.body).toContain('You are sending messages quickly. Wait a minute, then send again.');
    expect(messagesOf(chat.id)).toHaveLength(10);

    // Bo, a salesperson the chat did not go to: 403 for the page and his reply; an unknown chat 404.
    const boContext = await browser.newContext();
    const boPage = await boContext.newPage();
    const boErrors = collectFormErrors(boPage);
    await signInMember(boPage, bo);
    expect((await boPage.goto(`/app/chats/${chat.id}`))?.status()).toBe(403);
    expect((await post(boPage, `/app/chats/${chat.id}/messages`, { message: 'Mine now' })).status).toBe(403);
    expect((await post(boPage, `/app/chats/${chat.id}/close`, {})).status).toBe(403);
    expect((await boPage.goto('/app/chats/999999999'))?.status()).toBe(404);
    expect((await post(boPage, '/app/chats/999999999/messages', { message: 'Hello' })).status).toBe(404);
    await boPage.goto('/app/chats');
    await expect(boPage.locator(`#chat-row-${chat.id}`)).toHaveCount(0);
    await boContext.close();

    // Ann closes it; then her reply, a second close and the visitor's send are 409, and nothing is written.
    const annContext = await browser.newContext();
    const annPage = await annContext.newPage();
    const annErrors = collectFormErrors(annPage);
    await signInMember(annPage, ann);
    await annPage.goto(`/app/chats/${chat.id}`);
    expect((await post(annPage, `/app/chats/${chat.id}/messages`, { message: '' })).status).toBe(422);
    expect((await post(annPage, `/app/chats/${chat.id}/close`, {})).status).toBe(303);
    expect((await post(annPage, `/app/chats/${chat.id}/close`, {})).status).toBe(409);
    expect((await post(annPage, `/app/chats/${chat.id}/messages`, { message: 'Still there?' })).status).toBe(409);
    await annContext.close();
    const late = await post(page, '/chat/messages', { message: 'Hello?', back: car.path });
    expect(late.status).toBe(409);
    expect(late.body).toContain('This chat has ended.');
    expect(messagesOf(chat.id)).toHaveLength(10);
    expect(errors.filter((error) => !OWN.test(error))).toEqual([]);
    expect(boErrors.filter((error) => !OWN.test(error))).toEqual([]);
    expect(annErrors.filter((error) => !OWN.test(error))).toEqual([]);
});
