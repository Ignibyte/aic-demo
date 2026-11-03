import { expect, type Locator, type Page } from '@playwright/test';
import { literal } from './inventory';

// How long a look waits between keys to make a pause the box's 300 ms debounce answers, and how long between keys that
// make none.
export const PAUSE = 700;
export const QUICK = 40;

// One request a look saw the page make to a list's path, with what its answer held.
export interface SearchRequest {
    url: string;
    tesseraRequest: string;
    targets: string;
    templates: string[];
}

// A list's searches as a look records them, and the hold it can put on one search's answer.
export interface Searches {
    requests: SearchRequest[];
    hold: (query: string) => void;
    release: () => void;
}

// Records every request the page makes to the list's path, in order, and passes each answer on; an answer to the search
// a look holds is fetched from the server at once and passed on only once the look releases it. Tessera has aborted
// that request by then when a newer search superseded it, and the browser then refuses the answer.
export async function recordSearches(page: Page, path: string): Promise<Searches> {
    const requests: SearchRequest[] = [];
    let held = '';
    let release = (): void => undefined;
    let released = Promise.resolve();

    await page.route((url) => url.pathname === path, async (route) => {
        const request = route.request();
        const headers = await request.allHeaders();
        const response = await route.fetch({ maxRedirects: 0 });
        const body = await response.text();
        const contentType = response.headers()['content-type'] ?? '';

        requests.push({
            url: request.url(),
            tesseraRequest: headers['tessera-request'] ?? '',
            targets: headers['tessera-targets'] ?? '',
            templates: contentType.startsWith('text/vnd.tessera+html')
                ? [...body.matchAll(/<template data-target="([^"]*)">/g)].map((match) => match[1])
                : [],
        });

        if (held !== '' && new URL(request.url()).searchParams.get('q') === held) {
            await released;
        }

        await route.fulfill({ response, body }).catch(() => undefined);
    });

    return {
        requests,
        hold: (query: string) => {
            held = query;
            released = new Promise((resolve) => {
                release = () => resolve();
            });
        },
        release: () => {
            held = '';
            release();
        },
    };
}

// Each exchange Tessera settled since the look began watching, as its `tessera:exchange` event tells it.
export interface Exchange {
    kind: string;
    url: string;
    outcome: string;
}

// Starts collecting the page's `tessera:exchange` events.
export async function watchExchanges(page: Page): Promise<void> {
    await page.evaluate(() => {
        const seen: { kind: string; url: string; outcome: string }[] = [];
        Object.assign(window, { lotlineExchanges: seen });
        document.addEventListener('tessera:exchange', (event) => {
            const detail = (event as CustomEvent<{ kind: string; url: string; outcome: string }>).detail;
            seen.push({ kind: detail.kind, url: detail.url, outcome: detail.outcome });
        });
    });
}

// The exchanges collected so far.
export async function exchangesSeen(page: Page): Promise<Exchange[]> {
    return page.evaluate(() => (window as Window & { lotlineExchanges?: Exchange[] }).lotlineExchanges ?? []);
}

// The `q` of a request's URL; empty when it has none.
export function queryOf(url: string): string {
    return new URL(url).searchParams.get('q') ?? '';
}

// Waits for the page to have made `count` requests to the list, and gives their searches in order.
export async function expectSearches(searches: Searches, count: number): Promise<string[]> {
    await expect.poll(() => searches.requests.length).toBe(count);

    return searches.requests.map((request) => queryOf(request.url));
}

// The search box: its caret, read from the field.
export async function caret(box: Locator): Promise<number | null> {
    return box.evaluate((input) => (input as HTMLInputElement).selectionStart);
}

// The condition a search keeps over these columns, worked out here and not taken from the app.
export function searched(columns: string[], text: string): string {
    const pattern = literal(`%${text}%`);

    return `WHERE ${columns.map((column) => `${column} ILIKE ${pattern}`).join(' OR ')}`;
}

// How many entries the tab's history holds.
export async function historyLength(page: Page): Promise<number> {
    return page.evaluate(() => window.history.length);
}

// The inventory's search columns and the customers'.
export const VEHICLE_COLUMNS = ['make', 'model', 'vin'];
export const CUSTOMER_COLUMNS = ['name', 'email', 'phone'];

// The URL the inventory's filter form sends for a search with every other filter blank, as the browser builds it.
export function inventorySearch(text: string): string {
    return `/app/inventory?q=${encodeURIComponent(text).replaceAll('%20', '+')}&make=&status=&min_price=&max_price=&sort=`;
}

// The URL the customers' search form sends.
export function customerSearch(text: string): string {
    return `/app/customers?q=${encodeURIComponent(text).replaceAll('%20', '+')}`;
}
