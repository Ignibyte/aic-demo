import { expect, type Page } from '@playwright/test';
import { literal, sql } from './inventory';
import { insertStaff, mailFor, removeMail } from './leads';
import { signInAs } from './photos';

// Every member, lead and visitor the inbox looks make ends here, so their notifications, leads and mail are theirs
// alone and go afterwards.
export const DOMAIN = '@inbox-look.test';

// A member of staff of the looks' own.
export interface Member {
    id: string;
    email: string;
    password: string;
}

// An address of the looks' own, made unique for the run.
export function lookEmail(name: string): string {
    return `${name}.${Date.now()}${DOMAIN}`;
}

// Makes a member of staff with that role and a fresh password; their id is the user id their notifications carry.
export function insertMember(name: string, role = 'salesperson'): Member {
    const email = lookEmail(name);
    const password = insertStaff(email, `Inbox ${name}`, role);
    const [id] = sql(`SELECT id FROM users WHERE email = ${literal(email)}`);

    return { id, email, password };
}

// Signs the member in and lands on the dashboard.
export async function signInMember(page: Page, member: Member): Promise<void> {
    await signInAs(page, member.email, member.password);
}

// Stores a notification for the member, `minutesAgo` before now, and gives its id. The link is a path, or empty for
// none.
export function insertNotification(member: Member, subject: string, minutesAgo = 0, link = ''): number {
    const [id] = sql(
        'INSERT INTO aic_notifications (user_id, subject, body, link, created_at) '
            + `VALUES (${literal(member.id)}, ${literal(subject)}, ${literal(`${subject}.`)}, ${literal(link)}, `
            + `now() - interval '${minutesAgo} minutes') RETURNING id`,
    );

    return Number(id);
}

// How many of the member's notifications are unread.
export function unreadOf(member: Member): number {
    return Number(sql(`SELECT count(*) FROM aic_notifications WHERE user_id = ${literal(member.id)} AND read_at IS NULL`)[0]);
}

// Whether that notification has been read.
export function isRead(id: number): boolean {
    return sql(`SELECT read_at IS NOT NULL FROM aic_notifications WHERE id = ${id}`)[0] === 't';
}

// Gives the next lead to the member: a lead of the looks' own, assigned to the salesperson before them by id, becomes
// the newest, so the turn comes to them next (or to the first by id, which they are when none is before them).
export function turnTo(member: Member, vehicleId: number): void {
    sql(
        'INSERT INTO leads (vehicle_id, kind, name, email, phone, message, salesperson_id, status, created_at) '
            + `VALUES (${vehicleId}, 'inquiry', 'Inbox Turn', ${literal(lookEmail('turn'))}, '', 'Whose turn?', `
            + `(SELECT max(id) FROM users WHERE role = 'salesperson' AND id < ${member.id}), 'closed', now())`,
    );
}

// Deletes the looks' notifications, leads and members, and their mail in Mailpit.
export async function removeInboxLooks(): Promise<void> {
    sql(`DELETE FROM aic_notifications WHERE user_id IN (SELECT id::text FROM users WHERE email LIKE '%${DOMAIN}')`);
    sql(`DELETE FROM leads WHERE email LIKE '%${DOMAIN}'`);
    sql(`DELETE FROM users WHERE email LIKE '%${DOMAIN}'`);
    await removeMail(await mailFor(DOMAIN));
}

// Checks the bell's count: hidden at 0, else showing the number.
export async function expectBellCount(page: Page, count: number): Promise<void> {
    const badge = page.locator('#bell-count');

    if (count === 0) {
        await expect(badge).toBeHidden();

        return;
    }

    await expect(badge).toBeVisible();
    await expect(badge).toHaveText(String(count));
}

// One event of the bell's stream: its id (empty for none), and the patch's target, mode and markup.
export interface StreamEvent {
    id: string;
    target: string;
    mode: string;
    elements: string;
}

// Reads the bell's stream from the page itself, with its cookies and, when given, a `Last-Event-ID`, up to and with the
// first count it sends, then hangs up. Comments (keep-alives) are left out.
export async function readBell(page: Page, lastEventId = ''): Promise<StreamEvent[]> {
    return page.evaluate(async (lastEventId) => {
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), 15_000);
        const headers: Record<string, string> = { Accept: 'text/event-stream', 'Tessera-Request': 'stream', 'Tessera-Protocol': '2' };

        if (lastEventId !== '') {
            headers['Last-Event-ID'] = lastEventId;
        }

        const response = await fetch('/app/inbox/events', { headers, cache: 'no-store', redirect: 'manual', signal: controller.signal });

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
                    const event = { id: '', target: '', mode: 'morph', elements: '' };
                    let patch = false;

                    for (const line of frame.split('\n')) {
                        if (line.startsWith('id: ')) {
                            event.id = line.slice(4);
                        } else if (line === 'event: tessera-patch-elements') {
                            patch = true;
                        } else if (line.startsWith('data: target ')) {
                            event.target = line.slice(13);
                        } else if (line.startsWith('data: mode ')) {
                            event.mode = line.slice(11);
                        } else if (line.startsWith('data: elements ')) {
                            event.elements += line.slice(15);
                        }
                    }

                    if (!patch) {
                        continue;
                    }

                    events.push(event);

                    if (event.target === '@bell-count') {
                        return events;
                    }
                }
            }
        } finally {
            clearTimeout(timer);
            controller.abort();
        }
    }, lastEventId);
}
