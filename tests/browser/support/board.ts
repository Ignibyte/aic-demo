import { expect, type Locator, type Page } from '@playwright/test';
import { literal, sql } from './inventory';
import { insertStaff, firstCar } from './leads';
import { signInAs } from './photos';
import { type SentRequest } from './forms';

// Every lead and member the board looks make ends here, so their leads, moves, jobs and notifications are theirs alone
// and go afterwards.
export const DOMAIN = '@board-look.test';

// The board, as the list's tabs open it.
export const BOARD = '/app/leads?view=board';

// The board's stream.
export const EVENTS = '/app/leads/events';

// The five stages in the board's order, as the app stores them.
export const STAGES = ['new', 'contacted', 'appointment', 'won', 'lost'];

// An address of the looks' own, made unique for the run.
export function lookEmail(name: string): string {
    return `${name}.${Date.now()}${DOMAIN}`;
}

// A member of staff of the looks' own.
export interface Member {
    id: string;
    email: string;
    name: string;
    password: string;
}

// Makes a member of staff with that role and a fresh password.
export function insertMember(name: string, role = 'salesperson'): Member {
    const email = lookEmail(name.toLowerCase().replaceAll(' ', '.'));
    const password = insertStaff(email, name, role);
    const [id] = sql(`SELECT id FROM users WHERE email = ${literal(email)}`);

    return { id, email, name, password };
}

// Signs the member in and opens the board.
export async function openBoard(page: Page, member: Member): Promise<void> {
    await signInAs(page, member.email, member.password);
    await page.goto(BOARD);
    await expect(page.locator('#leads-board-columns')).toBeVisible();
}

// Stores a question about the first car in stock from a visitor of the looks' own, at that stage (its status in step,
// as the app keeps it), for that salesperson (null for none), and gives its id.
export function insertLead(name: string, stage: string, seller: Member | null): number {
    const car = firstCar();
    const status = stage === 'won' || stage === 'lost' ? 'closed' : 'open';
    const sellerId = seller === null ? 'NULL' : seller.id;
    const [id] = sql(
        'INSERT INTO leads (vehicle_id, kind, name, email, phone, message, salesperson_id, status, stage, created_at) '
            + `VALUES (${car.id}, 'inquiry', ${literal(name)}, ${literal(lookEmail('visitor'))}, '', 'Board look', ${sellerId}, `
            + `${literal(status)}, ${literal(stage)}, now()) RETURNING id`,
    );

    return Number(id);
}

// Deletes what the looks made: the Won jobs and notices about their leads, their members' notifications, their leads
// (their moves going with them) and their members.
export function removeBoardLooks(): void {
    const leads = `SELECT id::text FROM leads WHERE email LIKE '%${DOMAIN}'`;
    const users = `SELECT id::text FROM users WHERE email LIKE '%${DOMAIN}'`;
    sql(`DELETE FROM aic_jobs WHERE job = 'lead-won' AND payload::jsonb->>'lead' IN (${leads})`);
    sql(`DELETE FROM aic_jobs WHERE job = 'notify' AND payload::jsonb->>'user' IN (${users})`);
    sql(`DELETE FROM aic_notifications WHERE user_id::text IN (${users})`);
    sql(`DELETE FROM leads WHERE email LIKE '%${DOMAIN}'`);
    sql(`DELETE FROM users WHERE email LIKE '%${DOMAIN}'`);
}

// What the database holds of a lead: its stage and status.
export function stored(id: number): { stage: string; status: string } {
    const [line] = sql(`SELECT stage || '|' || status FROM leads WHERE id = ${id}`);
    const [stage, status] = (line ?? '|').split('|');

    return { stage, status };
}

// A lead's moves, oldest first, each as `from>to`.
export function moves(id: number): string[] {
    return sql(`SELECT from_stage || '>' || to_stage FROM lead_moves WHERE lead_id = ${id} ORDER BY id`);
}

// The newest move's id; 0 when there is none.
export function newestMove(): number {
    return Number(sql('SELECT coalesce(max(id), 0) FROM lead_moves')[0]);
}

// How many leads are at a stage.
export function countAt(stage: string): number {
    return Number(sql(`SELECT count(*) FROM leads WHERE stage = ${literal(stage)}`)[0]);
}

// The `lead-won` jobs queued for a lead.
export function wonJobs(id: number): number {
    return Number(sql(`SELECT count(*) FROM aic_jobs WHERE job = 'lead-won' AND payload::jsonb->>'lead' = ${literal(String(id))}`)[0]);
}

// A column, by its anchor.
export function column(page: Page, stage: string): Locator {
    return page.locator(`[data-t-anchor="leads-${stage}"]`);
}

// A lead's card, wherever it is.
export function card(page: Page, id: number): Locator {
    return page.locator(`#lead-card-${id}`);
}

// Holds that a column shows this count in its heading.
export async function expectCount(page: Page, stage: string, count: number): Promise<void> {
    await expect(page.locator(`#leads-${stage}-count`)).toHaveText(String(count));
}

// Marks each column's element and each card's, so a look can tell which ones a move replaced.
export async function markBoard(page: Page): Promise<void> {
    await page.evaluate(() => {
        for (const element of document.querySelectorAll('[data-t-anchor^="leads-"], [id^="lead-card-"]')) {
            (element as HTMLElement & { boardLook?: string }).boardLook = 'kept';
        }
    });
}

// Whether a column's element is still the one `markBoard` marked.
export async function kept(page: Page, stage: string): Promise<boolean> {
    return column(page, stage).evaluate((element) => (element as HTMLElement & { boardLook?: string }).boardLook === 'kept');
}

// Holds the board's stream back: it answers, in Tessera's protocol (without its header Tessera loads the page again),
// that the stream is over, so no stream patch can land in a look that counts its exchanges.
export async function holdStream(page: Page): Promise<void> {
    await page.route((url) => url.pathname === EVENTS, (route) => route.fulfill({
        status: 200,
        contentType: 'text/event-stream',
        headers: { 'Tessera-Protocol': '2' },
        body: 'event: tessera-close\ndata: \n\n',
    }));
}

// One event of the board's stream: its id (empty for none), and the patch's target, mode and markup.
export interface StreamEvent {
    id: string;
    target: string;
    mode: string;
    elements: string;
}

// Reads the board's stream from the page itself, with its cookies and, when given, a `Last-Event-ID`: its patches, up to
// `count` of them, or up to its first keep-alive comment, then hangs up.
export async function readBoard(page: Page, lastEventId: string, count: number): Promise<StreamEvent[]> {
    return page.evaluate(async ({ path, lastEventId, count }) => {
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), 15_000);
        const headers: Record<string, string> = { Accept: 'text/event-stream', 'Tessera-Request': 'stream', 'Tessera-Protocol': '2' };

        if (lastEventId !== '') {
            headers['Last-Event-ID'] = lastEventId;
        }

        const response = await fetch(path, { headers, cache: 'no-store', redirect: 'manual', signal: controller.signal });

        if (response.status !== 200 || response.body === null) {
            clearTimeout(timer);
            throw new Error(`The stream answered ${response.status}: ${await response.text()}`);
        }

        const reader = response.body.pipeThrough(new TextDecoderStream()).getReader();
        const events: { id: string; target: string; mode: string; elements: string }[] = [];
        let buffer = '';

        try {
            for (;;) {
                const { value, done } = await reader.read();

                if (done) {
                    return events;
                }

                buffer += value;
                const frames = buffer.split('\n\n');
                buffer = frames.pop() ?? '';

                for (const frame of frames) {
                    if (frame.startsWith(':')) {
                        return events;
                    }

                    const event = { id: '', target: '', mode: 'morph', elements: '' };

                    for (const line of frame.split('\n')) {
                        if (line.startsWith('id: ')) {
                            event.id = line.slice(4);
                        } else if (line.startsWith('data: target ')) {
                            event.target = line.slice(13);
                        } else if (line.startsWith('data: mode ')) {
                            event.mode = line.slice(11);
                        } else if (line.startsWith('data: elements ')) {
                            event.elements += line.slice(15);
                        }
                    }

                    events.push(event);

                    if (events.length === count) {
                        return events;
                    }
                }
            }
        } finally {
            clearTimeout(timer);
            controller.abort();
        }
    }, { path: EVENTS, lastEventId, count });
}

// Sends a stage move from the page itself, with its cookies: a PATCH, or a POST as a form without script sends it,
// urlencoded, with the token when one is given and Tessera's headers when targets are; gives its status and body.
export async function sendMove(
    page: Page,
    id: number,
    init: { method: string; stage: string; token: string; targets?: string; writeId?: string },
): Promise<{ status: number; body: string }> {
    return page.evaluate(async ({ path, init }) => {
        const headers: Record<string, string> = { 'Content-Type': 'application/x-www-form-urlencoded' };
        const fields = new URLSearchParams({ stage: init.stage });

        if (init.token !== '') {
            fields.set('aic_csrf', init.token);
        }

        if (init.targets !== undefined) {
            headers['Tessera-Request'] = 'action';
            headers['Tessera-Protocol'] = '2';
            headers['Tessera-Layout'] = 'lotline';
            headers['Tessera-Targets'] = init.targets;
        }

        if (init.writeId !== undefined) {
            headers['Tessera-Write-Id'] = init.writeId;
        }

        const response = await fetch(path, { method: init.method, headers, body: fields.toString(), cache: 'no-store', redirect: 'manual' });

        return { status: response.status, body: await response.text() };
    }, { path: `/app/leads/${id}/stage`, init });
}

// Holds the one request a move made: a Tessera action, PATCH to the lead's stage route with a fresh write id and the
// CSRF header, the form's `stage`, and targets naming the two columns by their anchors and nothing else; answered 200
// with a fragment for each.
export async function expectMove(requests: SentRequest[], id: number, from: string, to: string): Promise<void> {
    await expect.poll(() => requests.length).toBe(1);
    const [request] = requests;
    expect(request.method).toBe('PATCH');
    expect(request.resourceType).toBe('fetch');
    expect(request.path).toBe(`/app/leads/${id}/stage`);
    expect(request.tesseraRequest).toBe('action');
    expect(request.writeId).toMatch(/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/);
    expect(request.headers['x-aic-csrf'] ?? '').not.toBe('');
    expect(new URLSearchParams(request.body).get('stage')).toBe(to);
    const entries = request.targets.split(',').map((entry) => entry.trim().split(' '));
    expect(entries.map((entry) => entry[entry.length - 1]).sort()).toEqual([`@leads-${from}`, `@leads-${to}`].sort());
    expect(request.status).toBe(200);
    expect(request.contentType).toMatch(/^text\/vnd\.tessera\+html/);
    expect([...request.templates].sort()).toEqual(entries.map((entry) => entry[0]).sort());
    requests.length = 0;
}
