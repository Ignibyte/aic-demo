import { expect, test, type APIRequestContext, type Locator, type Page } from '@playwright/test';

// The profiler looks' own VINs (#25): fictional (`0LT`), `0LTPW` so no seeded vehicle and no native step shares them.
// The refused one has the saved one's letters with a wrong check digit.
export const VINS = {
    saved: '0LTPWPRF7SR999125',
    refused: '0LTPWPRF0SR999125',
};

// Skips the look on a site that answers without `X-AIC-Profile`: the profiler is off there (#25). gate:23 runs these
// looks only in its profiled pass, and DDEV has the profiler on.
export async function skipUnlessProfiled(request: APIRequestContext): Promise<void> {
    const response = await request.get('/health');
    test.skip(response.headers()['x-aic-profile'] === undefined, 'the profiler is off');
}

// The profiler's toolbar, which AIC puts at the end of every whole page while it is on (AIC #231).
export function toolbar(page: Page): Locator {
    return page.locator('aside.aic-toolbar');
}

// A request's figures as the toolbar writes them: its status, then its ms, statements and bytes.
function figures(status: string): string {
    return ` · ${status} · \\d+\\.\\d ms · \\d+ SQL · \\d+ bytes$`;
}

// The toolbar's line for one exchange: the Tessera request (or `load`), the method and path, the targets after `→`
// when it named any, and the figures. `targets` is a pattern the targets must end with; empty for none.
export function exchangeLine(tessera: string, method: string, path: string, targets: string, status: string): RegExp {
    const asked = targets === '' ? '' : ` → [^·]*${targets}`;

    return new RegExp(`^${tessera} ${method} ${path.replaceAll('/', '\\/')}${asked}${figures(status)}`);
}

// The links in the toolbar's list whose line matches.
export function exchanges(page: Page, line: RegExp): Locator {
    return toolbar(page).locator('.aic-toolbar-exchanges a', { hasText: line });
}

// Waits for the toolbar's list to hold at least `count` links whose line matches, and gives the one at `count`, in the
// order the exchanges happened.
export async function expectExchange(page: Page, line: RegExp, count: number, timeout = 10_000): Promise<Locator> {
    await expect.poll(() => exchanges(page, line).count(), { timeout }).toBeGreaterThanOrEqual(count);

    return exchanges(page, line).nth(count - 1);
}

// What a look checks of one profile beyond its heading and status: the query its Request tab names, the
// `Tessera-Location` its Tessera tab says the answer sent, and SQL its Database tab must not list.
export interface Profiled {
    query?: string;
    location?: RegExp;
    absent?: RegExp;
}

// A profile's Database tab caption when no statement repeats.
export const UNREPEATED = /^\d+ statement\(s\), 0 of them repeated$/;

// A profile's tab, opened by its link.
export async function openTab(detail: Page, name: string): Promise<void> {
    await detail.getByRole('navigation', { name: 'Profile' }).getByRole('link', { name }).click();
    await expect(detail.getByRole('navigation', { name: 'Profile' }).getByRole('link', { name })).toHaveAttribute('aria-current', 'page');
}

// The value cell of a profile table's row with this name.
function cell(detail: Page, table: string, name: string): Locator {
    return detail.getByRole('table', { name: table }).locator('tr', { has: detail.locator('td', { hasText: new RegExp(`^${name}$`) }) }).locator('td').last();
}

// Opens a profile link in a second tab and checks the profile it shows: the method and path as its heading, the
// status first in its figures, and what `profiled` names. The profiler's own pages are never recorded.
export async function expectProfile(page: Page, link: Locator, heading: string, status: string, profiled: Profiled = {}): Promise<void> {
    const href = await link.getAttribute('href');
    expect(href).toMatch(/^\/_profiler\/[0-9a-f]{32}$/);

    const detail = await page.context().newPage();
    await detail.goto(href ?? '');
    await expect(detail.locator('h2').first()).toHaveText(heading);
    await expect(detail.locator('main p').first()).toHaveText(new RegExp(`^${status} · `));

    if (profiled.query !== undefined) {
        await expect(cell(detail, 'The request', 'Query')).toHaveText(profiled.query);
    }

    if (profiled.location !== undefined) {
        await openTab(detail, 'Tessera');
        await expect(cell(detail, 'Answered', 'Tessera-Location')).toHaveText(profiled.location);
    }

    if (profiled.absent !== undefined) {
        await openTab(detail, 'Database');
        await expect(detail.locator('table.aic-table tbody tr').first()).toBeVisible();
        await expect(detail.locator('table.aic-table tbody td', { hasText: profiled.absent })).toHaveCount(0);
    }

    await detail.close();
}

// Collects every console error and page error but the browser's line for a 4xx answer, which a refused form gets.
export function collectProfilerErrors(page: Page): string[] {
    const errors: string[] = [];
    page.on('console', (message) => {
        if (message.type() === 'error' && !message.text().startsWith('Failed to load resource: the server responded with a status of 4')) {
            errors.push(message.text());
        }
    });
    page.on('pageerror', (error) => errors.push(error.message));

    return errors;
}
