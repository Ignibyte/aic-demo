import { expect, type Page } from '@playwright/test';

// The inventory results' Tessera path: the `content` region, the staff pages' unkeyed grid (`c0`), the panel
// `vehicles` and its part `table`, as the render pass keys them.
export const TABLE = 'content/c0/PanelGrid.1:vehicles/Panel.1:table';

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

// Waits for the one request a change makes, then holds it to a Tessera visit of the table answered with the table alone.
export async function expectTableVisit(requests: ListRequest[], path: string): Promise<void> {
    await expect.poll(() => requests.length).toBe(1);
    const [request] = requests;
    expect(request.resourceType).toBe('fetch');
    expect(pathOf(request.url)).toBe(path);
    expect(request.tesseraRequest).toBe('visit');
    expect(request.targets).toBe(TABLE);
    expect(request.contentType).toMatch(/^text\/vnd\.tessera\+html/);
    expectTesseraVary(request.vary);
    expect(request.templates).toEqual([TABLE]);
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
