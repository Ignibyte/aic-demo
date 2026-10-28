import { expect, type Page } from '@playwright/test';

// The inventory results' anchor (AIC #188): the filter form, the sortable heads and the page links target
// `@inventory-results`, and Tessera sends the results' path.
export const RESULTS = 'inventory-results';

// The path of the component carrying `data-t-anchor="NAME"`, found as Tessera and gate:23 find it: the enclosing
// region's name, then each `data-t-c` key from the outside in. Empty when no element, or more than one, carries it.
export async function anchorPath(page: Page, name: string): Promise<string> {
    return page.evaluate((anchor) => {
        const anchored = document.querySelectorAll(`[data-t-anchor="${anchor}"]`);

        if (anchored.length !== 1) {
            return '';
        }

        const keys: string[] = [];

        for (let element: Element | null = anchored[0]; element !== null; element = element.parentElement) {
            const key = element.getAttribute('data-t-c');
            const region = element.getAttribute('data-region');

            if (key !== null) {
                keys.unshift(key);
            }

            if (region !== null) {
                return [region, ...keys].join('/');
            }
        }

        return '';
    }, name);
}

// One request the page made to the inventory list, with what its answer held.
export interface ListRequest {
    resourceType: string;
    url: string;
    tesseraRequest: string;
    targets: string;
    contentType: string;
    vary: string;
    templates: string[];
}

// The names Tessera adds to every page answer's `Vary`, so a cache keeps its answers apart from the whole document; a
// server in front may add its own, such as DDEV's `Accept-Encoding`.
const VARY = ['Tessera-Request', 'Tessera-Protocol', 'Tessera-Targets', 'Tessera-Layout'];

// The answer's `Vary` names each of Tessera's.
export function expectTesseraVary(vary: string): void {
    expect(vary.split(',').map((name) => name.trim())).toEqual(expect.arrayContaining(VARY));
}

// Records every request the page makes to `/app/inventory`, in order, with its answer. The answer is taken through a
// route, since Tessera cancels a prefetched answer's stream once it has read it, and the browser then reports the
// request as failed.
export async function recordListRequests(page: Page): Promise<ListRequest[]> {
    const requests: ListRequest[] = [];

    await page.route((url) => url.pathname === '/app/inventory', async (route) => {
        const request = route.request();
        const response = await route.fetch({ maxRedirects: 0 });
        const body = await response.text();
        const contentType = response.headers()['content-type'] ?? '';
        const headers = await request.allHeaders();

        requests.push({
            resourceType: request.resourceType(),
            url: request.url(),
            tesseraRequest: headers['tessera-request'] ?? '',
            targets: headers['tessera-targets'] ?? '',
            contentType,
            vary: response.headers()['vary'] ?? '',
            templates: contentType.startsWith('text/vnd.tessera+html')
                ? [...body.matchAll(/<template data-target="([^"]*)">/g)].map((match) => match[1])
                : [],
        });
        await route.fulfill({ response, body });
    });

    return requests;
}

// Waits until the page has loaded, so Tessera, which starts once the document is parsed, handles the next click.
export async function ready(page: Page): Promise<void> {
    await page.waitForLoadState('load');
}

// A request's path and query, as the page writes its links.
export function pathOf(url: string): string {
    const parsed = new URL(url);

    return parsed.pathname + parsed.search;
}

// Waits for the one request a change makes, then holds it to a Tessera visit of the results, by the path their anchor
// has on the page and then the anchor, as protocol 2 ends each entry (AIC #194), answered with the results alone.
export async function expectTableVisit(page: Page, requests: ListRequest[], path: string): Promise<void> {
    await expect.poll(() => requests.length).toBe(1);
    const [request] = requests;
    const results = await anchorPath(page, RESULTS);
    expect(results).not.toBe('');
    expect(request.resourceType).toBe('fetch');
    expect(pathOf(request.url)).toBe(path);
    expect(request.tesseraRequest).toBe('visit');
    expect(request.targets).toBe(`${results} @${RESULTS}`);
    expect(request.contentType).toMatch(/^text\/vnd\.tessera\+html/);
    expectTesseraVary(request.vary);
    expect(request.templates).toEqual([results]);
    requests.length = 0;
}

// Marks the window and the sidebar's navigation, so a document load or a redrawn sidebar shows as a lost mark.
export async function markPage(page: Page): Promise<void> {
    await page.evaluate(() => {
        Object.assign(window, { lotlineMark: 'kept' });
    });
    await page.getByRole('navigation', { name: 'Main' }).evaluate((nav) => {
        Object.assign(nav, { lotlineMark: 'kept' });
    });
}

// Both marks are still there: the same document and the same sidebar element.
export async function expectMarksKept(page: Page): Promise<void> {
    expect(await page.evaluate(() => (window as Window & { lotlineMark?: string }).lotlineMark)).toBe('kept');
    expect(await page.getByRole('navigation', { name: 'Main' }).evaluate((nav) => (nav as Element & { lotlineMark?: string }).lotlineMark)).toBe('kept');
}

// Collects every console error and page error, a refusal under the page's Content-Security-Policy among them.
export function collectErrors(page: Page): string[] {
    const errors: string[] = [];
    page.on('console', (message) => {
        if (message.type() === 'error') {
            errors.push(message.text());
        }
    });
    page.on('pageerror', (error) => {
        errors.push(error.message);
    });

    return errors;
}
