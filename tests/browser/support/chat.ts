import { expect, type Locator, type Page } from '@playwright/test';
import { literal, sql } from './inventory';
import { firstCar, insertStaff, mailFor, removeMail, type Car } from './leads';
import { signInAs } from './photos';
import { SLUG } from './storefront';

// Every member, lead and visitor the chat looks make ends here, and every visitor they type is named `Chat Look …`,
// so their chats, messages, jobs, notifications and mail are theirs alone and go afterwards.
export const DOMAIN = '@chat-look.test';

// The visitor's stream.
export const EVENTS = '/chat/events';

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

// Signs the member in.
export async function signInMember(page: Page, member: Member): Promise<void> {
    await signInAs(page, member.email, member.password);
}

// The car a look chats about: the newest in stock, with a lead of the looks' own for that member as its newest, so a
// chat started on its page goes to them.
export function carFor(member: Member): Car {
    const car = firstCar();
    sql(
        'INSERT INTO leads (vehicle_id, kind, name, email, phone, message, salesperson_id, status, stage, created_at) '
            + `VALUES (${car.id}, 'inquiry', 'Chat Look Lead', ${literal(lookEmail('lead'))}, '', 'Whose chat?', ${member.id}, `
            + "'open', 'new', now())",
    );

    return car;
}

// A car in stock with no lead at all, so a chat on its page goes to the sales manager.
export function carWithoutLeads(): Car {
    const [row] = sql(
        `SELECT id || '|' || ${SLUG} || '|' || trim(year || ' ' || make || ' ' || model || ' ' || trim) || '|' || vin `
            + "FROM vehicles WHERE status = 'in_stock' AND NOT EXISTS (SELECT 1 FROM leads l WHERE l.vehicle_id = vehicles.id) "
            + 'ORDER BY added_on DESC, vin ASC LIMIT 1',
    );
    const [id, slug, title, vin] = row.split('|');

    return { id: Number(id), path: `/cars/${slug}`, title, vin };
}

// What the storefront calls a car, and so the chat: its year, make and model.
export function chatTitle(car: Car): string {
    return sql(`SELECT year || ' ' || make || ' ' || model FROM vehicles WHERE id = ${car.id}`)[0];
}

// The active sales manager with the lowest id, whom a chat on a car with no lead goes to.
export function salesManager(): string {
    return sql("SELECT id FROM users WHERE role = 'sales_manager' AND deactivated_on IS NULL ORDER BY id LIMIT 1")[0] ?? '';
}

// A chat as Postgres keeps it.
export interface StoredChat {
    id: number;
    staffId: string;
    status: string;
    lastSeq: number;
    visitorSeen: number;
    staffSeen: number;
    name: string;
    email: string;
    carTitle: string;
}

// The chats of a visitor of the looks' own, by the name they gave, oldest first.
export function chatsOf(name: string): StoredChat[] {
    return sql(
        "SELECT id || '|' || coalesce(staff_id::text, '') || '|' || status || '|' || last_seq || '|' || visitor_seen || '|' "
            + "|| staff_seen || '|' || visitor_name || '|' || coalesce(visitor_email, '') || '|' || car_title "
            + `FROM chats WHERE visitor_name = ${literal(name)} ORDER BY id`,
    ).map((row) => {
        const [id, staffId, status, lastSeq, visitorSeen, staffSeen, visitorName, email, carTitle] = row.split('|');

        return {
            id: Number(id),
            staffId,
            status,
            lastSeq: Number(lastSeq),
            visitorSeen: Number(visitorSeen),
            staffSeen: Number(staffSeen),
            name: visitorName,
            email,
            carTitle,
        };
    });
}

// A chat's messages, oldest first, each `seq|side|text` with its line breaks as `\n`.
export function messagesOf(chatId: number): string[] {
    return sql(`SELECT seq || '|' || side || '|' || replace(body, E'\\n', '\\n') FROM chat_messages WHERE chat_id = ${chatId} ORDER BY seq`);
}

// How many chat-transcript jobs wait for that chat.
export function transcriptJobs(chatId: number): number {
    return Number(sql(`SELECT count(*) FROM aic_jobs WHERE job = 'chat-transcript' AND payload::jsonb->>'chat' = ${literal(String(chatId))}`)[0]);
}

// Deletes what the looks made: their chats' jobs and the notifications about them, their chats (their messages going
// with them), their members' notifications, their leads and members, and their mail.
export async function removeChatLooks(): Promise<void> {
    const chats = "SELECT id::text FROM chats WHERE visitor_name LIKE 'Chat Look%'";
    const users = `SELECT id::text FROM users WHERE email LIKE '%${DOMAIN}'`;
    sql(`DELETE FROM aic_jobs WHERE job = 'chat-transcript' AND payload::jsonb->>'chat' IN (${chats})`);
    sql(`DELETE FROM aic_jobs WHERE job = 'notify' AND (payload::jsonb->>'user' IN (${users}) OR payload::jsonb->>'email' LIKE '%${DOMAIN}')`);
    sql(`DELETE FROM aic_jobs WHERE job = 'notify' AND payload::jsonb->>'subject' LIKE 'New chat about%' AND payload::jsonb->>'body' LIKE 'Chat Look%'`);
    sql(`DELETE FROM aic_notifications WHERE user_id IN (${users}) OR (subject LIKE 'New chat about%' AND body LIKE 'Chat Look%')`);
    sql("DELETE FROM chats WHERE visitor_name LIKE 'Chat Look%'");
    sql(`DELETE FROM leads WHERE email LIKE '%${DOMAIN}'`);
    sql(`DELETE FROM users WHERE email LIKE '%${DOMAIN}'`);
    await removeMail(await mailFor(DOMAIN));
}

// What a visitor types into the start form.
export interface Visitor {
    name: string;
    email: string;
    message: string;
}

// The start dock, the open dock, and the open dock's parts.
export function startDock(page: Page): Locator {
    return page.locator('#chat-start');
}

export function dock(page: Page): Locator {
    return page.locator('#chat-dock');
}

export function box(page: Page): Locator {
    return page.locator('#chat-send-form-message');
}

export function lines(page: Page): Locator {
    return page.locator('[data-t-anchor="chat-messages"] .chat-bubble');
}

// Opens the car's start dock, fills it and starts the chat, then waits for the open dock.
export async function startChat(page: Page, car: Car, visitor: Visitor): Promise<void> {
    await page.goto(car.path);
    await startDock(page).locator('summary').click();
    await page.locator('#chat-name').fill(visitor.name);
    await page.locator('#chat-email').fill(visitor.email);
    await page.locator('#chat-message').fill(visitor.message);
    await page.locator('#chat-start-go').click();
    await expect(dock(page)).toBeVisible();
}

// Marks the dock's element, so a look can tell it is still the same node.
export async function markDock(page: Page): Promise<void> {
    await dock(page).evaluate((element) => {
        (element as HTMLElement & { chatLook?: string }).chatLook = 'kept';
    });
}

// Whether the dock is still the node `markDock` marked.
export async function dockKept(page: Page): Promise<boolean> {
    return dock(page).evaluate((element) => (element as HTMLElement & { chatLook?: string }).chatLook === 'kept');
}

// One event of a chat's stream: its name, its id (empty for none), and its target or scope, mode, markup and signals.
export interface ChatEvent {
    event: string;
    id: string;
    target: string;
    mode: string;
    elements: string;
    signals: string;
}

// Reads a chat's stream from the page itself, with its cookies and, when given, a `Last-Event-ID`: its events, up to
// `count` of them, or up to its first keep-alive comment, then hangs up.
export async function readStream(page: Page, path: string, lastEventId: string, count: number): Promise<ChatEvent[]> {
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
        const events: { event: string; id: string; target: string; mode: string; elements: string; signals: string }[] = [];
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

                    const event = { event: '', id: '', target: '', mode: 'morph', elements: '', signals: '' };

                    for (const line of frame.split('\n')) {
                        if (line.startsWith('event: ')) {
                            event.event = line.slice(7);
                        } else if (line.startsWith('id: ')) {
                            event.id = line.slice(4);
                        } else if (line.startsWith('data: target ')) {
                            event.target = line.slice(13);
                        } else if (line.startsWith('data: scope ')) {
                            event.target = line.slice(12);
                        } else if (line.startsWith('data: mode ')) {
                            event.mode = line.slice(11);
                        } else if (line.startsWith('data: elements ')) {
                            event.elements += `${line.slice(15)}\n`;
                        } else if (line.startsWith('data: signals ')) {
                            event.signals += line.slice(14);
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
    }, { path, lastEventId, count });
}

// Sends a form from the page itself, with its cookies and the page's token unless `token` says otherwise: a plain
// post, or Tessera's submit when `targets` names its targets; gives the status, the Location and the body.
export async function post(
    page: Page,
    path: string,
    fields: Record<string, string>,
    options: { token?: string; targets?: string } = {},
): Promise<{ status: number; location: string; body: string }> {
    return page.evaluate(async ({ path, fields, options }) => {
        const meta = document.querySelector('meta[name="tessera-csrf-token"]');
        const token = options.token ?? meta?.getAttribute('content') ?? '';
        const body = new URLSearchParams({ ...fields, aic_csrf: token, aic_website: fields.aic_website ?? '' });
        const headers: Record<string, string> = { 'Content-Type': 'application/x-www-form-urlencoded' };

        if (options.targets !== undefined) {
            headers['Tessera-Request'] = 'submit';
            headers['Tessera-Protocol'] = '2';
            headers['Tessera-Layout'] = document.querySelector('meta[name="tessera-layout"]')?.getAttribute('content') ?? '';
            headers['Tessera-Targets'] = options.targets;
        }

        const response = await fetch(path, { method: 'POST', headers, body, redirect: 'manual' });

        return { status: response.status === 0 ? 303 : response.status, location: response.headers.get('Location') ?? '', body: await response.text() };
    }, { path, fields, options });
}
