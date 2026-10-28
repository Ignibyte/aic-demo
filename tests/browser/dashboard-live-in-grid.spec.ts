import { expect, test, type Page } from '@playwright/test';
import { signIn } from './support/dashboard';
import { LIVE, recordPolls } from './support/live';
import { anchorPath, collectErrors, ready } from './support/tessera';

// Every `Tessera-Targets` line a refresh of the dashboard sent, as the browser sent it.
function refreshTargets(page: Page): string[] {
    const lines: string[] = [];
    page.on('request', (request) => {
        const headers = request.headers();

        if (headers['tessera-request'] === 'refresh') {
            lines.push(headers['tessera-targets'] ?? '');
        }
    });

    return lines;
}

test('the live part leads the panel grid, and its poll sends its props and its anchor (#15)', async ({ page }) => {
    test.setTimeout(60_000);
    const errors = collectErrors(page);
    const sent = refreshTargets(page);

    await signIn(page);
    await ready(page);

    // The live part is the grid's first item, beside the three charts, and the grid is all `content` holds.
    const live = page.locator(`[data-t-anchor="${LIVE}"]`);
    await expect(live).toHaveCount(1);
    await expect(live).toHaveAttribute('data-t-props', /.+/);
    const placed = await live.evaluate((element) => {
        const grid = element.parentElement;
        const charts = ['dashboard-sales', 'dashboard-makes', 'dashboard-salespeople']
            .map((anchor) => document.querySelector(`[data-t-anchor="${anchor}"]`)?.parentElement === grid);

        return {
            first: grid?.firstElementChild === element,
            charts,
            items: grid?.children.length ?? 0,
            gridAlone: grid?.parentElement?.getAttribute('data-region') === 'content' && grid.parentElement.children.length === 1,
        };
    });
    expect(placed).toEqual({ first: true, charts: [true, true, true], items: 4, gridAlone: true });

    // Each poll names the part's path, then its props token, then its anchor (protocol 2), and answers 204 while
    // nothing changes.
    const path = await anchorPath(page, LIVE);
    expect(path).not.toBe('');
    const token = await live.getAttribute('data-t-props') ?? '';
    const requests = await recordPolls(page);
    await expect.poll(() => requests.filter((request) => request.finished).length, { timeout: 15_000 }).toBeGreaterThanOrEqual(2);
    expect(requests.map((request) => request.status)).toEqual(requests.map(() => 204));
    expect(sent.length).toBeGreaterThanOrEqual(2);

    for (const line of sent) {
        expect(line).toBe(`${path} ${token} @${LIVE}`);
    }

    // No console error so far.
    expect(errors).toEqual([]);

    // A refresh whose props were altered answers 400, decided before the page is built. The page sends it, with its
    // session: the session cookie is `__Host-` and secure, which Playwright's own request context keeps from a site
    // served over plain HTTP, as gate:23 serves the compiled one.
    const middle = Math.floor(token.length / 2);
    const altered = token.slice(0, middle) + (token[middle] === 'A' ? 'B' : 'A') + token.slice(middle + 1);
    const refused = await page.evaluate(async (targets) => {
        const response = await fetch('/app', {
            headers: {
                'Tessera-Request': 'refresh',
                'Tessera-Protocol': '2',
                'Tessera-Layout': 'lotline',
                'Tessera-Targets': targets,
            },
        });

        return response.status;
    }, `${path} ${altered} @${LIVE}`);
    expect(refused).toBe(400);

    // Chromium reports that 400 in the console; it is the only error.
    expect(errors).toEqual(['Failed to load resource: the server responded with a status of 400 (Bad Request)']);
});
