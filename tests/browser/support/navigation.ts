import { expect, type Locator, type Page } from '@playwright/test';
import { literal, sql } from './inventory';

// The gate's user, made by `bin/aic user:add` for each run: an admin whose name is its login.
const login = process.env.AIC_GATE_LOGIN ?? '';
const password = process.env.AIC_GATE_PASSWORD ?? '';

// The app's name, which closes every page's title.
const APP = 'Lotline';

// The regions a visit naming one of them is answered with, as AIC refreshes the layout's others.
const REGIONS = ['content', 'footer', 'header', 'sidebar'];

// The sidebar's links, each the section of the pages under it.
export type Section = 'Dashboard' | 'Inventory' | 'Customers' | 'Sales' | 'Theme';

// One page of the walk: the link that leads to it, the section the sidebar marks, and its heading and title as
// Postgres gives them, read from the path the link names.
export interface Step {
    name: string;
    section: Section;
    link: (page: Page) => Locator;
    heading: (path: string) => string;
    title: (heading: string) => string;
}

// One page as the browser showed it: its path, the scroll it had when it was left.
export interface Visited {
    step: Step;
    path: string;
    scroll: number;
}

// The page's own title, then the app's name.
function titled(heading: string): string {
    return `${heading} · ${APP}`;
}

// The one row Postgres gives, or a failure naming the query.
function one(statement: string): string {
    const rows = sql(statement);
    expect(rows, statement).toHaveLength(1);

    return rows[0];
}

// The last segment of a path, a record's id or VIN.
function key(path: string): string {
    return decodeURIComponent(path.split('/').pop() ?? '');
}

// What staff call a vehicle, as the vehicle's and the sale's pages write it.
const VEHICLE = "trim(v.year || ' ' || v.make || ' ' || v.model || ' ' || v.trim)";

// A sidebar link, by its name.
function sidebar(name: Section): (page: Page) => Locator {
    return (page) => page.getByRole('navigation', { name: 'Main' }).getByRole('link', { name, exact: true });
}

// The last record link in a list's table, near the foot of the list, where a page scrolled down still shows it.
function lastRecord(table: string, prefix: string): (page: Page) => Locator {
    return (page) => page.locator(`#${table} tbody a[href^="${prefix}"]`).last();
}

// A page whose heading is fixed.
function fixed(heading: string): (path: string) => string {
    return () => heading;
}

const dashboard: Step = {
    name: 'the dashboard',
    section: 'Dashboard',
    link: sidebar('Dashboard'),
    heading: () => `Welcome, ${one(`SELECT name FROM users WHERE email = ${literal(login)}`)}`,
    title: () => titled('Dashboard'),
};

// The walk from the dashboard through every page and back to it: each step's link is on the page before it.
export const WALK: Step[] = [
    { name: 'the inventory', section: 'Inventory', link: sidebar('Inventory'), heading: fixed('Inventory'), title: titled },
    {
        name: 'a vehicle',
        section: 'Inventory',
        link: lastRecord('vehicles-table', '/app/inventory/'),
        heading: (path) => one(`SELECT ${VEHICLE} FROM vehicles v WHERE v.vin = ${literal(key(path))}`),
        title: titled,
    },
    { name: 'the customers', section: 'Customers', link: sidebar('Customers'), heading: fixed('Customers'), title: titled },
    {
        name: 'a customer',
        section: 'Customers',
        link: lastRecord('customers-table', '/app/customers/'),
        heading: (path) => one(`SELECT name FROM customers WHERE id = ${Number(key(path))}`),
        title: titled,
    },
    { name: 'the sales', section: 'Sales', link: sidebar('Sales'), heading: fixed('Sales'), title: titled },
    {
        name: 'a sale',
        section: 'Sales',
        link: lastRecord('sales-table', '/app/sales/'),
        heading: (path) => `Sale of the ${one(`SELECT ${VEHICLE} FROM sales s JOIN vehicles v ON v.id = s.vehicle_id WHERE s.id = ${Number(key(path))}`)}`,
        title: titled,
    },
    { name: 'the theme guide', section: 'Theme', link: sidebar('Theme'), heading: fixed('Theme'), title: titled },
    dashboard,
];

// Where the walk starts, the page sign-in lands on.
export const START: Step = dashboard;

// Signs the gate's user in through `/app`, which sends a signed-out visitor to sign in and back as a whole document.
// The sign-in page is titled by its heading too.
export async function signIn(page: Page): Promise<void> {
    await page.goto('/app');
    await expect(page).toHaveTitle(titled('Sign in'));
    await expect(page.locator('[data-region="header"] h1')).toHaveText('Sign in');
    await page.locator('#sign-in-email').fill(login);
    await page.locator('#sign-in-password').fill(password);
    await page.locator('#sign-in-submit').click();
    await expect(page).toHaveURL(/\/app$/);
    await page.waitForLoadState('load');
}

// One request the page made, with what its answer held.
export interface NavRequest {
    method: string;
    resourceType: string;
    path: string;
    tesseraRequest: string;
    targets: string;
    status: number;
    contentType: string;
    fullVisit: string;
    templates: string[];
}

// A request's path and query, as the page writes its links.
export function pathOf(url: string): string {
    const parsed = new URL(url);

    return parsed.pathname + parsed.search;
}

// Records every request the page makes but its assets, the dashboard's refreshes and the bell's stream, in order, with its answer. The
// answer is taken through a route, with no redirect followed, so what the server sent is what is recorded. A refresh,
// which the dashboard's poll sends every five seconds while it is open, and the bell's stream, which stays open, go on
// to the network unrecorded.
export async function recordRequests(page: Page): Promise<NavRequest[]> {
    const requests: NavRequest[] = [];

    await page.route((url) => !url.pathname.startsWith('/assets/'), async (route) => {
        const request = route.request();
        const headers = await request.allHeaders();

        if (headers['tessera-request'] === 'refresh' || headers['tessera-request'] === 'stream') {
            await route.fallback();

            return;
        }

        const response = await route.fetch({ maxRedirects: 0 });
        const body = await response.text();
        const answer = response.headers();
        const contentType = answer['content-type'] ?? '';

        requests.push({
            method: request.method(),
            resourceType: request.resourceType(),
            path: pathOf(request.url()),
            tesseraRequest: headers['tessera-request'] ?? '',
            targets: headers['tessera-targets'] ?? '',
            status: response.status(),
            contentType,
            fullVisit: answer['tessera-full-visit'] ?? '',
            templates: contentType.startsWith('text/vnd.tessera+html')
                ? [...body.matchAll(/<template data-target="([^"]*)">/g)].map((match) => match[1])
                : [],
        });
        await route.fulfill({ response, body });
    });

    return requests;
}

// Waits for the one request a visit makes, and holds it to a Tessera visit of the path, with no document. A visit
// that names a region is answered with every region; one back to the page sign-in loaded names none, and is answered
// with the whole document, which Tessera commits in place.
export async function expectVisit(requests: NavRequest[], path: string, whole = false): Promise<void> {
    await expect.poll(() => requests.length).toBeGreaterThan(0);
    const [request] = requests;
    expect(requests.map((sent) => `${sent.resourceType} ${sent.method} ${sent.path}`)).toEqual([`fetch GET ${path}`]);
    expect(request.tesseraRequest).toBe('visit');
    expect(request.status).toBe(200);

    if (whole) {
        expect(request.contentType).toMatch(/^text\/html/);
    } else {
        expect(request.contentType).toMatch(/^text\/vnd\.tessera\+html/);
        expect([...request.templates].sort()).toEqual(REGIONS);
    }

    requests.length = 0;
}

// Waits for the one request a page load makes with JavaScript off, and holds it to a whole document of the path.
export async function expectDocument(requests: NavRequest[], path: string): Promise<void> {
    await expect.poll(() => requests.length).toBeGreaterThan(0);
    expect(requests.map((sent) => `${sent.resourceType} ${sent.method} ${sent.path} ${sent.status}`)).toEqual([`document GET ${path} 200`]);
    expect(requests[0].tesseraRequest).toBe('');
    expect(requests[0].contentType).toMatch(/^text\/html/);
    requests.length = 0;
}

// The page shows the step: its URL, its title and heading as Postgres gives them, and the sidebar's one current link.
export async function expectPage(page: Page, step: Step, path: string): Promise<void> {
    const heading = step.heading(path);
    await expect(page).toHaveURL(path);
    await expect(page).toHaveTitle(step.title(heading));
    await expect(page.locator('[data-region="header"] h1')).toHaveText(heading);
    const current = page.getByRole('navigation', { name: 'Main' }).locator('a[aria-current="page"]');
    await expect(current).toHaveCount(1);
    await expect(current).toHaveText(step.section);
}

// Waits until the page's scroll is where it should be and has rested, so Tessera has recorded it on the entry.
export async function expectScroll(page: Page, scroll: number, name: string): Promise<void> {
    await expect.poll(() => page.evaluate(() => Math.round(window.scrollY)), { message: `the scroll of ${name}` }).toBe(scroll);
    await page.waitForTimeout(300);
}

// The page's scroll, as Tessera records it.
export async function scrollOf(page: Page): Promise<number> {
    return page.evaluate(() => Math.round(window.scrollY));
}
