import { expect, type Locator, type Page } from '@playwright/test';
import { sql } from './inventory';
import { insertVehicle, testVehicle, type TestVehicle } from './vehicles';

// The calculator looks' own cars (#26): fictional (`0LT`), `0LTPWFNC` so no seeded vehicle and no native step shares
// them, check digits worked out by hand. Each is the test vehicle, priced $17,900.
export const VINS = {
    store: '0LTPWFNC1SR999126',
    staff: '0LTPWFNC3SR999127',
    noScript: '0LTPWFNC5SR999128',
    profiled: '0LTPWFNC7SR999129',
};

// The test vehicle's price, and the down payment the calculator starts at: 10% of it, rounded down to $100.
export const PRICE = 17_900;
export const DOWN = 1_700;

// The dealership's default APR now, in percent: the profile's, or the standard 6.90% while it has none.
export function profileApr(): number {
    const [basisPoints] = sql('SELECT coalesce(max(default_apr_bp), 690) FROM dealership_profile');

    return Number(basisPoints) / 100;
}

// Puts one of the looks' cars in stock, as the seed would.
export function insertCar(vin: string): TestVehicle {
    const vehicle = testVehicle(vin);
    insertVehicle(vehicle);

    return vehicle;
}

// What the calculator is told: the down payment, the trade-in, the APR in percent and the term in months.
export interface Terms {
    down: number;
    trade: number;
    apr: number;
    months: number;
}

// What the calculator shows for those terms on the test vehicle, written as `money` writes them.
export interface Figures {
    payment: string;
    financed: string;
    interest: string;
}

const dollars = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' });

// The figures for those terms, by the formula the calculator's signals use.
export function figures(terms: Terms, price = PRICE): Figures {
    const financed = Math.max(0, price - terms.down - terms.trade);
    const rate = Math.max(0, terms.apr) / 1200;
    const growth = Math.pow(1 + rate, terms.months);
    const payment = rate === 0 ? financed / terms.months : (financed * rate * growth) / (growth - 1);

    return {
        payment: dollars.format(payment),
        financed: dollars.format(financed),
        interest: dollars.format(payment * terms.months - financed),
    };
}

// The calculator's fields and figures, by their ids.
export function calculator(page: Page): Record<'root' | 'body' | 'range' | 'down' | 'term' | 'apr' | 'trade' | 'payment' | 'financed' | 'interest' | 'warning' | 'covered' | 'examples', Locator> {
    return {
        root: page.locator('#finance'),
        body: page.locator('#finance-calculator'),
        range: page.locator('#finance-down-range'),
        down: page.locator('#finance-down'),
        term: page.locator('#finance-term'),
        apr: page.locator('#finance-apr'),
        trade: page.locator('#finance-trade'),
        payment: page.locator('#finance-payment'),
        financed: page.locator('#finance-financed'),
        interest: page.locator('#finance-interest'),
        warning: page.locator('#finance-warning'),
        covered: page.locator('#finance-covered'),
        examples: page.locator('#finance-examples'),
    };
}

// Holds the calculator's three figures to what those terms give.
export async function expectFigures(page: Page, terms: Terms): Promise<void> {
    const shown = calculator(page);
    const expected = figures(terms);
    await expect(shown.payment).toHaveText(expected.payment);
    await expect(shown.financed).toHaveText(expected.financed);
    await expect(shown.interest).toHaveText(expected.interest);
}

// Types into a field one key at a time, as a person does, after clearing it; each key is one `input` event.
export async function typeInto(field: Locator, text: string): Promise<void> {
    await field.fill('');
    await field.pressSequentially(text);
}

// Starts recording every request the page makes from now on but a stream's, which a staff page's bell keeps open and
// reconnects on its own; the calculator's look holds the list to empty.
export function recordRequests(page: Page): string[] {
    const requests: string[] = [];
    page.on('request', (request) => {
        if (request.resourceType() !== 'eventsource') {
            requests.push(`${request.method()} ${request.url()}`);
        }
    });

    return requests;
}

// The calculator's whole walk on the test vehicle, from its defaults at the given APR: the figures follow each
// keystroke in each field, the range and the number field stay in step both ways, the range's max is the price, and
// the cover note and the warning come and go.
export async function walkCalculator(page: Page, apr: number): Promise<void> {
    const shown = calculator(page);
    await expect(shown.body).toBeVisible();
    await expect(shown.examples).toBeHidden();

    // Nothing in it can make a request or run a statement of its own: no form, no action, no event handler, no effect.
    await expect(shown.root.locator('form, [data-on], [data-effect], [data-init], [action]')).toHaveCount(0);
    expect(await shown.root.evaluate((root) => [root, ...root.querySelectorAll('*')].flatMap((element) => element.getAttributeNames()).filter((name) => /^data-(on|effect|init)/.test(name) || name.includes('@')))).toEqual([]);
    await expect(shown.range).toHaveAttribute('max', String(PRICE));
    await expect(shown.down).toHaveValue(String(DOWN));
    await expect(shown.range).toHaveValue(String(DOWN));
    await expect(shown.term).toHaveValue('60');
    await expect(shown.apr).toHaveValue(String(apr));
    await expect(shown.trade).toHaveValue('0');
    await expectFigures(page, { down: DOWN, trade: 0, apr, months: 60 });
    await expect(shown.warning).toBeHidden();
    await expect(shown.covered).toBeHidden();

    // Each keystroke in the down payment moves the figures and the range with it.
    await shown.down.fill('');
    await shown.down.press('3');
    await expectFigures(page, { down: 3, trade: 0, apr, months: 60 });
    await shown.down.press('0');
    await shown.down.press('0');
    await expectFigures(page, { down: 300, trade: 0, apr, months: 60 });
    await shown.down.press('0');
    await expectFigures(page, { down: 3_000, trade: 0, apr, months: 60 });
    await expect(shown.range).toHaveValue('3000');

    // The range moves the number field and the figures.
    await shown.range.fill('5000');
    await expect(shown.down).toHaveValue('5000');
    await expectFigures(page, { down: 5_000, trade: 0, apr, months: 60 });

    // The term, the APR and the trade-in.
    await shown.term.selectOption('36');
    await expectFigures(page, { down: 5_000, trade: 0, apr, months: 36 });
    await typeInto(shown.apr, '4.5');
    await expectFigures(page, { down: 5_000, trade: 0, apr: 4.5, months: 36 });
    await typeInto(shown.apr, '0');
    await expectFigures(page, { down: 5_000, trade: 0, apr: 0, months: 36 });
    await typeInto(shown.trade, '2500');
    await expectFigures(page, { down: 5_000, trade: 2_500, apr: 0, months: 36 });

    // Nothing down and no trade-in over 36 months at 9.9% is more than 3% of the price a month: the warning shows.
    await typeInto(shown.trade, '0');
    await typeInto(shown.down, '0');
    await typeInto(shown.apr, '9.9');
    await expectFigures(page, { down: 0, trade: 0, apr: 9.9, months: 36 });
    await expect(shown.payment).toHaveClass(/\btext-warning\b/);
    await expect(shown.warning).toBeVisible();
    await shown.term.selectOption('72');
    await expect(shown.payment).not.toHaveClass(/\btext-warning\b/);
    await expect(shown.warning).toBeHidden();

    // A down payment and trade-in that cover the price leave nothing to finance.
    await typeInto(shown.down, '15000');
    await typeInto(shown.trade, '2900');
    await expect(shown.covered).toBeVisible();
    await expectFigures(page, { down: 15_000, trade: 2_900, apr: 9.9, months: 72 });
    await expect(shown.payment).toHaveText('$0.00');
    await typeInto(shown.trade, '2899');
    await expect(shown.covered).toBeHidden();
}

// Holds the no-script table to the three example terms at the default down payment and that APR: AIC's `Table` atom,
// its two money columns, head and cells, marked to sit at the end and set there by the theme (AIC #240).
export async function expectExamples(page: Page, apr: number): Promise<void> {
    const shown = calculator(page);
    await expect(shown.body).toBeHidden();
    await expect(shown.examples).toBeVisible();
    const table = shown.examples.locator('table.aic-table');
    await expect(table).toHaveCount(1);
    await expect(table.locator('caption')).toHaveText(
        `With $1,700 down and no trade-in, at ${apr.toFixed(2)}% APR, before tax and fees.`,
    );
    const heads = table.locator('thead th');
    await expect(heads).toHaveText(['Term', 'Monthly payment', 'Total interest']);
    const rows = table.locator('tbody tr');
    await expect(rows).toHaveCount(3);

    for (const [index, months] of [36, 48, 60].entries()) {
        const expected = figures({ down: DOWN, trade: 0, apr, months });
        await expect(rows.nth(index).locator('td')).toHaveText([`${months} months`, expected.payment, expected.interest]);
    }

    for (const column of [heads, ...[0, 1, 2].map((index) => rows.nth(index).locator('td'))]) {
        await expect(column.nth(0)).not.toHaveClass(/aic-align-end/);

        for (const money of [1, 2]) {
            await expect(column.nth(money)).toHaveClass('aic-align-end');
            await expect(column.nth(money)).toHaveCSS('text-align', 'end');
        }
    }
}
