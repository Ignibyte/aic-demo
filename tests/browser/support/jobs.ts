import { execFileSync } from 'node:child_process';
import { expect, type Locator, type Page } from '@playwright/test';
import { literal, sql } from './inventory';
import { insertStaff, mailFor, removeMail } from './leads';
import { signInAs } from './photos';
import { retry, runSchedule, unusedMinute, work } from './schedules';

// The jobs looks (#35). Their members and mail end in DOMAIN, so their rows on the page are found by those addresses.
// The worker runs on this host as the other looks run it (`./schedules`), and the dev fault switch, the DDEV command
// `ddev queue-work-mail-down`, runs it in the web container against the same Postgres with the mail out of reach.
export const DOMAIN = '@jobs-look.test';

// The digest's subject, as the dealership's default name starts it.
export const DIGEST = 'Ridgeway Motors on ';

// The gate user, an admin.
const login = process.env.AIC_GATE_LOGIN ?? '';
const password = process.env.AIC_GATE_PASSWORD ?? '';

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
    const memberPassword = insertStaff(email, name, role);
    const [id] = sql(`SELECT id FROM users WHERE email = ${literal(email)}`);

    return { id, email, name, password: memberPassword };
}

// Signs the member in and lands on the dashboard.
export async function signInMember(page: Page, member: Member): Promise<void> {
    await signInAs(page, member.email, member.password);
}

// Signs the gate user, an admin, in and opens the jobs page.
export async function openJobs(page: Page): Promise<void> {
    await signInAs(page, login, password);
    await page.goto('/app/jobs');
    await expect(page).toHaveTitle('Jobs · Lotline');
}

// The highest job and failed job ids now, and the database's time, so a look settles only what came after.
export interface Marks {
    job: number;
    failed: number;
    at: string;
}

export function marks(): Marks {
    const [job] = sql('SELECT coalesce(max(id), 0) FROM aic_jobs');
    const [failed] = sql('SELECT coalesce(max(id), 0) FROM aic_failed_jobs');
    const [at] = sql('SELECT now()');

    return { job: Number(job), failed: Number(failed), at };
}

// Queues the nightly digest for an unused 21:00 and gives schedule:run's line.
export function queueDigest(): string {
    return runSchedule(unusedMinute('21:00'));
}

// Runs the dev fault switch: `ddev queue-work-mail-down`, the worker with the mail server out of reach, and gives its
// last line. The web container's PHP prints each refused connection's warning to standard output before it.
export function workWithMailFault(): string {
    return execFileSync('ddev', ['queue-work-mail-down', '1000'], { encoding: 'utf8' }).trim().split('\n').at(-1) ?? '';
}

// Makes the mail to that address due now, as a look waits for no retry's delay.
export function makeDue(email: string): void {
    sql(`UPDATE aic_jobs SET available_at = now() WHERE job = 'notify' AND payload::jsonb ->> 'email' = ${literal(email)}`);
}

// The mail to that address in the queue: its attempts, newest first.
export function queuedMail(email: string): number[] {
    return sql(`SELECT attempts FROM aic_jobs WHERE job = 'notify' AND payload::jsonb ->> 'email' = ${literal(email)} ORDER BY id DESC`).map(Number);
}

// The failed mail to that address: its ids, newest first.
export function failedMail(email: string): number[] {
    return sql(`SELECT id FROM aic_failed_jobs WHERE job = 'notify' AND payload::jsonb ->> 'email' = ${literal(email)} ORDER BY id DESC`).map(Number);
}

// The rows of "In the queue" whose text holds that.
export function queueRows(page: Page, text: string): Locator {
    return page.locator('#jobs-queue tbody tr').filter({ hasText: text });
}

// A row's cells as shown.
export async function cells(row: Locator): Promise<string[]> {
    return (await row.locator('td').allInnerTexts()).map((text) => text.trim());
}

// A failed job's card.
export function failedCard(page: Page, id: number): Locator {
    return page.locator(`#failed-${id}`);
}

// The About of every row of "In the queue", in the order shown.
export async function queueAbouts(page: Page): Promise<string[]> {
    return (await page.locator('#jobs-queue tbody tr td:nth-child(2)').allInnerTexts()).map((text) => text.trim());
}

// Whether a time AIC wrote (ISO 8601 in UTC) is after now, with a second's slack.
export function isLater(iso: string): boolean {
    return Date.parse(iso) > Date.now() - 1_000;
}

// Settles what a look left: every job queued since `since` is made due and worked with the mail back, every other
// job that failed since is queued again with `bin/aic queue:retry` and worked, and the looks' members, notifications
// and mail go.
export async function removeJobsLooks(since: Marks): Promise<void> {
    sql(`UPDATE aic_jobs SET available_at = now() WHERE id > ${since.job}`);
    work();
    sql(`DELETE FROM aic_failed_jobs WHERE id > ${since.failed} AND payload LIKE '%${DOMAIN}%'`);

    for (const id of sql(`SELECT id FROM aic_failed_jobs WHERE id > ${since.failed}`)) {
        retry(Number(id));
    }

    work();
    sql(`DELETE FROM aic_jobs WHERE id > ${since.job} AND payload LIKE '%${DOMAIN}%'`);
    sql(`DELETE FROM aic_notifications WHERE user_id IN (SELECT id::text FROM users WHERE email LIKE '%${DOMAIN}')`);
    sql(`DELETE FROM aic_notifications WHERE created_at >= ${literal(since.at)} AND subject LIKE ${literal(`${DIGEST}%`)}`);
    sql(`DELETE FROM users WHERE email LIKE '%${DOMAIN}'`);

    for (const text of [DOMAIN, DIGEST]) {
        await removeMail(await mailFor(text));
    }
}
