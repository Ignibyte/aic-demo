import { type Page, type Response } from '@playwright/test';

// The style guide's resilience cases (#34), one page each under `/theme/resilience/`, in the order the guide lists them.
export const CASES = {
    expired: '/theme/resilience/expired',
    gateway: '/theme/resilience/gateway',
    refused: '/theme/resilience/refused',
    feed: '/theme/resilience/feed',
    protocol: '/theme/resilience/protocol',
    fullVisit: '/theme/resilience/full-visit',
};

// Chromium's console line for a fetch or a document answered with an error status.
const FAILED = 'Failed to load resource: the server responded with a status of ';

// Collects every console error and page error, but Chromium's line for a request answered with one of the statuses the
// case expects: the 419, 422, 503 or 504 it shows on purpose is not an error of the page's.
export function collectCaseErrors(page: Page, statuses: number[]): string[] {
    const expected = statuses.map((status) => `${FAILED}${status}`);
    const errors: string[] = [];
    page.on('console', (message) => {
        if (message.type() === 'error' && !expected.some((line) => message.text().startsWith(line))) {
            errors.push(message.text());
        }
    });
    page.on('pageerror', (error) => {
        errors.push(error.message);
    });

    return errors;
}

// Marks the window, so a full load shows as the mark gone.
export async function markWindow(page: Page): Promise<void> {
    await page.evaluate(() => {
        Object.assign(window, { resilienceMark: 'kept' });
    });
}

// Whether the window still carries the mark: the same document.
export async function windowKept(page: Page): Promise<boolean> {
    return page.evaluate(() => (window as Window & { resilienceMark?: string }).resilienceMark === 'kept');
}

// Counts, on the document, every time the page hears a Tessera event, beside the page's own count: the events bubble
// there, and `tessera:expired` is raised there. A cancelled event still reaches the document.
export async function listenFor(page: Page, name: string): Promise<void> {
    await page.evaluate((event) => {
        const counts: Record<string, number> = { [event]: 0 };
        Object.assign(window, { resilienceHeard: counts });
        document.addEventListener(event, () => {
            counts[event] += 1;
        });
    }, name);
}

// How many times the document heard the event since `listenFor()`.
export async function heard(page: Page, name: string): Promise<number> {
    return page.evaluate((event) => (window as Window & { resilienceHeard?: Record<string, number> }).resilienceHeard?.[event] ?? 0, name);
}

// The answer to the next POST to the path.
export function nextPost(page: Page, path: string): Promise<Response> {
    return page.waitForResponse((response) => new URL(response.url()).pathname === path && response.request().method() === 'POST');
}
