import { expect, type Page } from '@playwright/test';
import { type SentRequest } from './forms';
import { literal, sql } from './inventory';
import { insertStaff, mailFor, mailText, removeMail } from './leads';
import { signInAs } from './photos';
import { work } from './schedules';

// Every member of staff the settings looks make ends here, so their links, notifications and mail are theirs alone and
// go afterwards. The looks put back the dealership's profile and any admin they set aside.
export const DOMAIN = '@settings-look.test';

// A member of staff of the looks' own.
export interface Member {
    id: string;
    email: string;
    name: string;
    password: string;
}

// An address of the looks' own, made unique for the run.
export function lookEmail(name: string): string {
    return `${name.toLowerCase().replaceAll(' ', '.')}.${Date.now()}${DOMAIN}`;
}

// Makes a member of staff with that role and a fresh password.
export function insertMember(name: string, role: string): Member {
    const email = lookEmail(name);
    const password = insertStaff(email, name, role);
    const [id] = sql(`SELECT id FROM users WHERE email = ${literal(email)}`);

    return { id, email, name, password };
}

// Signs the member in and lands on the dashboard.
export async function signInMember(page: Page, member: Member): Promise<void> {
    await signInAs(page, member.email, member.password);
}

// The member's row as stored: their name, role and the day they were deactivated (empty while active).
export function storedMember(id: string): { name: string; role: string; deactivatedOn: string } {
    const [row] = sql(`SELECT name || '|' || role || '|' || coalesce(deactivated_on::text, '') FROM users WHERE id = ${id}`);
    const [name, role, deactivatedOn] = row.split('|');

    return { name, role, deactivatedOn };
}

// The dealership's profile row as found, as JSON; empty when there is none and the defaults stand.
export function savedProfile(): string[] {
    return sql('SELECT row_to_json(p)::text FROM dealership_profile p');
}

// Puts the profile row back as `savedProfile()` found it, or removes it when there was none.
export function restoreProfile(saved: string[]): void {
    sql('DELETE FROM dealership_profile');

    if (saved.length === 1) {
        sql(`INSERT INTO dealership_profile OVERRIDING SYSTEM VALUE SELECT * FROM json_populate_record(NULL::dealership_profile, ${literal(saved[0])}::json)`);
    }
}

// The dealership's name now: the profile's, or the default.
export function dealershipName(): string {
    return sql("SELECT coalesce((SELECT name FROM dealership_profile WHERE slot = 'main'), 'Ridgeway Motors')")[0];
}

// Deactivates every active admin but the one kept, as of today, and gives their ids, so the kept one is the last.
export function setAsideAdmins(kept: Member): string[] {
    return sql(`UPDATE users SET deactivated_on = current_date WHERE role = 'admin' AND deactivated_on IS NULL AND id <> ${kept.id} RETURNING id`);
}

// Reactivates the admins `setAsideAdmins()` set aside.
export function restoreAdmins(ids: string[]): void {
    if (ids.length > 0) {
        sql(`UPDATE users SET deactivated_on = NULL WHERE id IN (${ids.join(', ')})`);
    }
}

// Runs the worker, so the queued notifications are kept and mailed, then deletes the looks' staff (their links go with
// them), their kept notifications and their mail.
export async function removeSettingsLooks(): Promise<void> {
    work();
    sql(`DELETE FROM aic_notifications WHERE user_id IN (SELECT id::text FROM users WHERE email LIKE '%${DOMAIN}')`);
    sql(`DELETE FROM users WHERE email LIKE '%${DOMAIN}'`);
    await removeMail(await mailFor(DOMAIN));
}

// Runs the worker and gives the path of every password link mailed to the address, oldest first, with the mails'
// subjects and texts.
export async function mailedLinks(email: string): Promise<{ subject: string; text: string; path: string }[]> {
    work();
    const mails = await mailFor(email);
    const links: { subject: string; text: string; path: string }[] = [];

    for (const mail of mails.reverse()) {
        const text = await mailText(mail);
        const url = /https?:\/\/\S+\/password\/\S+/.exec(text)?.[0] ?? '';
        links.push({ subject: mail.subject, text, path: url === '' ? '' : new URL(url).pathname });
    }

    return links;
}

// Waits for the one request a submit answered in place makes, and holds it to that status answered as a fragment of
// `content`, as forms' `expectRefusedInPlace()` does for a 422.
export async function expectAnsweredInPlace(requests: SentRequest[], action: string, status: number): Promise<void> {
    await expect.poll(() => requests.length).toBe(1);
    const [request] = requests;
    expect(request.method).toBe('POST');
    expect(request.path).toBe(action);
    expect(request.tesseraRequest).toBe('submit');
    expect(request.status).toBe(status);
    expect(request.contentType).toMatch(/^text\/vnd\.tessera\+html/);
    expect(request.templates).toEqual(['content']);
    requests.length = 0;
}

// Fills the profile form's fields that are given.
export async function fillProfile(page: Page, values: Partial<Record<'name' | 'address' | 'phone' | 'opens_at' | 'closes_at' | 'aged_stock_days', string>>): Promise<void> {
    for (const [field, value] of Object.entries(values)) {
        await page.locator(`#profile-${field}`).fill(value);
    }
}
