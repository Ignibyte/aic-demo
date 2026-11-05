import { existsSync } from 'node:fs';
import { join } from 'node:path';
import { expect, test, type Page } from '@playwright/test';
import { aic } from './support/console';
import { signIn } from './support/dashboard';
import { collectErrors, ready } from './support/tessera';

// The Coverage page (#38) holds one row for each surface `bin/aic code:coverage` lists. Its rows are data in code
// (`config/coverage.php`), never read from the command while serving; this look holds them to the command. A feature
// dropped later leaves its surface unused while its row still says used, and the look fails.

const PAGE = '/theme/coverage';

// One surface, as `bin/aic code:coverage --json` lists it.
interface Surface {
    group: string;
    name: string;
    used: boolean;
}

// One row, as the page shows it: the group of its table, then its five cells.
interface Row {
    group: string;
    name: string;
    state: string;
    example: string;
    href: string;
    test: string;
    reason: string;
}

// What the command lists, in its order.
function surfaces(): Surface[] {
    const answer = JSON.parse(aic(['code:coverage', '--json'])) as { data: { surfaces: Surface[] } };

    return answer.data.surfaces.map(({ group, name, used }) => ({ group, name, used }));
}

// What the page shows, its tables in order and each table's rows in order.
async function rows(page: Page): Promise<Row[]> {
    return page.locator('table[id^="coverage-"] tbody tr').evaluateAll((elements) =>
        elements.map((element) => {
            const cells = [...element.querySelectorAll('td')].map((cell) => (cell.textContent ?? '').trim());
            const link = element.querySelectorAll('td')[2]?.querySelector('a');

            return {
                group: (element.closest('table')?.id ?? '').replace(/^coverage-/, ''),
                name: cells[0] ?? '',
                state: cells[1] ?? '',
                example: cells[2] ?? '',
                href: link?.getAttribute('href') ?? '',
                test: cells[3] ?? '',
                reason: cells[4] ?? '',
            };
        }),
    );
}

test('the page lists every surface code:coverage lists, with its state, and each used row names a kept test', async ({ page }) => {
    const errors = collectErrors(page);
    const listed = surfaces();
    await page.goto(PAGE);
    await ready(page);
    const shown = await rows(page);

    // The same surfaces, in the same groups and order.
    expect(shown.map(({ group, name }) => `${group} ${name}`)).toEqual(listed.map(({ group, name }) => `${group} ${name}`));

    // A used surface has its page and its test and no reason; an unused one has a reason and neither of the others.
    listed.forEach((surface, index) => {
        const row = shown[index];

        if (surface.used) {
            expect(row, surface.name).toMatchObject({ state: 'Used', reason: '' });
            expect(row.href, surface.name).toMatch(/^\//);
            expect(row.example, surface.name).toBe(row.href);
            expect(row.test, surface.name).toMatch(/\.spec\.ts$/);
            expect(existsSync(join('tests', 'browser', row.test)), `${surface.name}: ${row.test}`).toBe(true);
        } else {
            expect(row, surface.name).toMatchObject({ state: 'Reasoned', example: '', href: '', test: '' });
            expect(row.reason, surface.name).not.toBe('');
        }
    });

    // The command that checks it, in AIC's code block.
    await expect(page.locator('pre.aic-code code')).toHaveText('bin/aic code:coverage --missing');
    expect(errors).toEqual([]);
});

test('every page a row links to opens, signed in as an admin, with no console error', async ({ page }) => {
    test.setTimeout(180_000);
    const errors = collectErrors(page);
    await signIn(page);
    await page.goto(PAGE);
    await ready(page);
    const paths = [...new Set((await rows(page)).map(({ href }) => href).filter((href) => href !== ''))];
    expect(paths.length).toBeGreaterThan(0);

    for (const path of paths) {
        const response = await page.goto(path);
        expect(response?.status() ?? 0, path).toBeLessThan(400);
    }

    expect(errors).toEqual([]);
});

test('the style guide links to the page', async ({ page }) => {
    const errors = collectErrors(page);
    await page.goto('/theme');
    await ready(page);
    await page.locator(`a[href="${PAGE}"]`).click();
    await expect(page).toHaveURL(new RegExp(`${PAGE}$`));
    await expect(page.locator('table[id="coverage-contracts"]')).toBeVisible();
    expect(errors).toEqual([]);
});

test.describe('with no script', () => {
    test.use({ javaScriptEnabled: false });

    test('the page lists the same rows', async ({ page }) => {
        const listed = surfaces();
        await page.goto(PAGE);
        expect((await rows(page)).map(({ name }) => name)).toEqual(listed.map(({ name }) => name));
    });
});
