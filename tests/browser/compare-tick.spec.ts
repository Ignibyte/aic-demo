// The comparison's ticks (#33): up to three cars ticked anywhere on the storefront, held in Tessera's document signals
// across enhanced visits (another page of the list, a car's page, Back), a fourth refused in the browser with a
// message, Clear, and a reload starting with none.

import { expect, test } from '@playwright/test';
import { MAKE, dock, expectChosen, insertCompareCars, removeCompareCars, tick } from './support/compare';
import { documentKept, markDocument } from './support/storefront';
import { collectErrors, ready } from './support/tessera';

let slugs: string[] = [];

test.beforeEach(() => {
    removeCompareCars();
    slugs = insertCompareCars(14);
});
test.afterEach(() => removeCompareCars());

test('ticks follow the visitor across the storefront, three at most', async ({ page }) => {
    const errors = collectErrors(page);
    const parts = dock(page);
    await page.goto(`/cars?make=${MAKE}`);
    await ready(page);
    await markDocument(page);

    // With nothing ticked the bar stays out of the way.
    await expect(parts.bar).toBeHidden();

    await tick(page, slugs[0]).click();
    await tick(page, slugs[1]).click();
    await expectChosen(page, [slugs[0], slugs[1]]);

    // The list's second page arrives as a fragment of the results; the bar holds, and its ticks start unticked.
    await page.locator('#car-pages').getByRole('link', { name: '2', exact: true }).click();
    await expect(tick(page, slugs[12])).toBeVisible();
    await expect(tick(page, slugs[12])).not.toBeChecked();
    await tick(page, slugs[12]).click();
    await expectChosen(page, [slugs[0], slugs[1], slugs[12]]);

    // A fourth is refused: its box goes unticked, the three stay, and the bar says why. No request is made.
    const requests: string[] = [];
    page.on('request', (request) => {
        requests.push(request.url());
    });
    await tick(page, slugs[13]).click();
    await expect(parts.full).toBeVisible();
    await expect(parts.full).toHaveText('You can compare up to three cars. Untick one to add another.');
    await expect(tick(page, slugs[13])).not.toBeChecked();
    await expectChosen(page, [slugs[0], slugs[1], slugs[12]]);
    expect(requests).toEqual([]);

    // Unticking one hides the message, and another car may be ticked.
    await tick(page, slugs[12]).click();
    await expect(parts.full).toBeHidden();
    await expectChosen(page, [slugs[0], slugs[1]]);

    // A car's page, by an enhanced visit: the same document, the bar kept, the car's own tick unticked until ticked.
    await page.locator(`#car-${slugs[13]}`).click();
    await expect(page).toHaveURL(new RegExp(`/cars/${slugs[13]}$`));
    await expect(tick(page, slugs[13])).not.toBeChecked();
    await expectChosen(page, [slugs[0], slugs[1]]);
    await tick(page, slugs[13]).click();
    await expectChosen(page, [slugs[0], slugs[1], slugs[13]]);

    // Back to the second page: the ticks show what is chosen.
    await page.goBack();
    await expect(tick(page, slugs[13])).toBeChecked();
    await expect(tick(page, slugs[12])).not.toBeChecked();

    // And the first page, by a filtered list's link: its two ticks are ticked.
    await page.locator('#car-pages').getByRole('link', { name: '1', exact: true }).click();
    await expect(tick(page, slugs[0])).toBeChecked();
    await expect(tick(page, slugs[1])).toBeChecked();
    await expect(tick(page, slugs[2])).not.toBeChecked();
    expect(await documentKept(page)).toBe(true);
    expect(errors).toEqual([]);

    // Clear empties the selection and unticks every box.
    await parts.clear.click();
    await expect(parts.bar).toBeHidden();
    await expect(tick(page, slugs[0])).not.toBeChecked();
    await expect(tick(page, slugs[1])).not.toBeChecked();

    // A reload starts with none.
    await tick(page, slugs[2]).click();
    await expectChosen(page, [slugs[2]]);
    await page.reload();
    await ready(page);
    await expect(parts.bar).toBeHidden();
    await expect(tick(page, slugs[2])).not.toBeChecked();
    expect(errors).toEqual([]);
});
