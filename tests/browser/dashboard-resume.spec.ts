import { expect, test } from '@playwright/test';
import { signIn } from './support/dashboard';
import { BUYER_NAME, VINS, expectFeed, fingerprint, insertLiveLooks, insertLiveSale, login, recordStreams, removeLiveLooks, shownFeed } from './support/live';
import { today } from './support/sales';
import { collectErrors, expectMarksKept, markPage, ready } from './support/tessera';

test.beforeEach(() => {
    removeLiveLooks();
    insertLiveLooks();
});
test.afterEach(() => removeLiveLooks());

// What ends every finished stream, after which Tessera does not reconnect; an answer without it reads as a drop.
const CLOSE = 'event: tessera-close\ndata: reason done\n\n';

test('after a drop the stream resumes from Last-Event-ID and brings the sale recorded meanwhile', async ({ page }) => {
    const errors = collectErrors(page);
    const { connections, documents } = recordStreams(page);
    let opened = (): void => {};
    let reconnected = (): void => {};
    let release = (): void => {};
    const firstOpen = new Promise<void>((resolve) => {
        opened = resolve;
    });
    const secondHeld = new Promise<void>((resolve) => {
        reconnected = resolve;
    });
    const letThrough = new Promise<void>((resolve) => {
        release = resolve;
    });
    let answered = 0;

    // The first connection is the server's own answer for four looks, ended without Tessera's close, as a connection
    // that drops would end; the second is held until the look lets it through to the server, as it is.
    await page.route((url) => url.pathname === '/app/events', async (route) => {
        answered += 1;

        if (answered === 1) {
            opened();
            const response = await route.fetch({ url: `${route.request().url()}&polls=4` });
            const body = await response.text();
            expect(body.endsWith(CLOSE)).toBe(true);
            await route.fulfill({ response, body: body.slice(0, -CLOSE.length) });

            return;
        }

        reconnected();
        await letThrough;
        await route.continue();
    });

    await signIn(page);
    await ready(page);
    await markPage(page);
    documents.length = 0;

    // A sale recorded while the first connection is open reaches the page with the fingerprint as the event's id.
    await firstOpen;
    insertLiveSale(VINS[0], 19800);
    const afterFirst = fingerprint();
    await expectFeed(page);
    expect((await shownFeed(page))[0]).toEqual([today(), '2023 Probe Hatch SE', BUYER_NAME, login, '$19,800']);

    // The connection drops; Tessera reconnects, naming the last id it received.
    await secondHeld;
    insertLiveSale(VINS[1], 22300);
    release();
    await expect.poll(() => connections.length).toBe(2);
    expect(connections[1]?.lastEventId).toBe(afterFirst);
    expect(connections[1]?.tesseraRequest).toBe('stream');

    // The resumed stream brings the sale recorded during the drop, with no reload.
    await expectFeed(page);
    expect((await shownFeed(page))[0]).toEqual([today(), '2023 Probe Hatch SE', BUYER_NAME, login, '$22,300']);
    await expectMarksKept(page);
    expect(documents).toEqual([]);
    expect(errors).toEqual([]);
});
