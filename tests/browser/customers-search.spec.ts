import { expect, test, type Page } from '@playwright/test';
import { literal } from './support/inventory';
import { countWhere, PAGE, pageNames, removeTestCustomers, shownNames, signIn } from './support/customers';

// What the table's summary says for that many customers at that page.
function summary(count: number, page = 1): string {
    const pages = Math.ceil(count / PAGE);
    const customers = `${count} ${count === 1 ? 'customer' : 'customers'}`;

    return pages > 1 ? `${customers}, page ${page} of ${pages}` : customers;
}

// The condition the list's search keeps, worked out here and not taken from the app.
function searched(text: string): string {
    const pattern = literal(`%${text}%`);

    return `WHERE name ILIKE ${pattern} OR email ILIKE ${pattern} OR phone ILIKE ${pattern}`;
}

// Searches the list and checks it shows exactly the rows Postgres gives for the same search.
async function expectSearch(page: Page, text: string): Promise<void> {
    await page.locator('#filter-q').fill(text);
    await page.locator('#customers-filters').getByRole('button', { name: 'Filter' }).click();

    const where = searched(text);
    const expected = pageNames(where);
    expect(expected.length, text).toBeGreaterThan(0);
    await expect(page.locator('#customers-table-summary')).toHaveText(summary(countWhere(where)));
    await expect(shownNames(page)).toHaveText(expected);
    await expect(page.locator('#filter-q')).toHaveValue(text);
}

test.beforeEach(() => removeTestCustomers());

test('the sidebar links to the customers, and paging and searching give the rows Postgres gives', async ({ page }) => {
    await signIn(page);

    const customers = page.getByRole('navigation', { name: 'Main' }).getByRole('link', { name: 'Customers' });
    await expect(customers).toHaveAttribute('href', '/app/customers');
    await customers.click();
    await expect(page).toHaveURL(/\/app\/customers$/);
    await expect(customers).toHaveAttribute('aria-current', 'page');

    const total = countWhere('');
    expect(total).toBeGreaterThan(PAGE);
    await expect(page.locator('#customers-table-summary')).toHaveText(summary(total));
    await expect(shownNames(page)).toHaveText(pageNames(''));

    const link = (label: string) => page.locator('#customers-pages a', { hasText: new RegExp(`^${label}$`) });
    await expect(link('1')).toHaveAttribute('aria-current', 'page');
    await link('2').click();
    await expect(link('2')).toHaveAttribute('aria-current', 'page');
    await expect(page.locator('#customers-table-summary')).toHaveText(summary(total, 2));
    await expect(shownNames(page)).toHaveText(pageNames('', PAGE));

    // Each name links to its customer's page.
    const first = shownNames(page).first().getByRole('link');
    await expect(first).toHaveAttribute('href', /^\/app\/customers\/\d+$/);

    // By name, by part of an email, and by part of a phone.
    await expectSearch(page, 'ada');
    await expectSearch(page, 'kowalski');
    await expectSearch(page, '555-012');

    // A search that keeps more than a page: its page links keep the search.
    await expectSearch(page, 'example.test');
    await expect(link('2')).toHaveAttribute('href', '/app/customers?q=example.test&page=2');
    await link('2').click();
    await expect(shownNames(page)).toHaveText(pageNames(searched('example.test'), PAGE));

    // A search that keeps no one says so.
    await page.locator('#filter-q').fill('no such customer');
    await page.locator('#customers-filters').getByRole('button', { name: 'Filter' }).click();
    await expect(page.locator('#customers-table-summary')).toHaveText('0 customers');
    await expect(page.locator('#customers-table-empty')).toHaveText('No customer matches. Try another search, or clear it.');
});
