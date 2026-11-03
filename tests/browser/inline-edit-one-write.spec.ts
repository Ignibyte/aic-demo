// A row's save is one write (#28): a double Enter sends one PUT, since the row's form sends nothing while its save is
// out, and that PUT sent again with its `Tessera-Write-Id` is answered from AIC's write ledger without saving again. A
// field carries `data-ignore-morph` while it is typed into, so an answer that lands meanwhile leaves what was typed.

import { expect, test } from '@playwright/test';
import { signIn, sql } from './support/inventory';
import { collectFormErrors, recordRequests } from './support/forms';
import {
    countWrites, expectRowAction, holdWrites, removeInlineLooks, sendAgain, VEHICLES, vehicleRow, VINS,
} from './support/inline-edit';
import { ready } from './support/tessera';
import { insertVehicle, storedVehicle, testVehicle } from './support/vehicles';

const VIN = VINS.edited;
const PATH = `/app/inventory/${VIN}`;
const ANCHOR = `vehicle-${VIN}`;

test.beforeEach(() => removeInlineLooks());
test.afterEach(() => removeInlineLooks());

test('a double Enter saves once, and the save sent again with its write id is answered from the ledger', async ({ page }) => {
    test.setTimeout(60_000);
    const errors = collectFormErrors(page);
    insertVehicle(testVehicle(VIN));
    await signIn(page);
    await page.goto(VEHICLES);
    await ready(page);
    const row = vehicleRow(page, VIN);
    const requests = await recordRequests(page);
    const writes = countWrites(page);
    const release = await holdWrites(page);
    await row.locator('a', { hasText: '$17,900' }).click();
    await expectRowAction(requests, 'GET', `${PATH}/row/edit`, ANCHOR, 200);
    const price = page.locator(`#list_price-${ANCHOR}`);

    // Two Enters while the first save is out: the form carries `data-loading`, and the second sends nothing.
    await page.keyboard.type('18000');
    await price.press('Enter');
    await expect(row.locator('form')).toHaveAttribute('data-loading', '');
    await price.press('Enter');
    await page.waitForTimeout(500);
    expect(writes.sent).toBe(1);
    release();
    const saved = await expectRowAction(requests, 'PUT', `${PATH}/row`, ANCHOR, 200);
    await expect(row).toContainText('$18,000');
    await page.waitForTimeout(500);
    expect(writes.sent).toBe(1);
    expect(requests).toEqual([]);
    expect(storedVehicle(VIN)?.listPrice).toBe(18000);

    // Someone changes the price meanwhile; the save sent again with its write id gets the first answer, the row with
    // $18,000, and is not applied again.
    sql(`UPDATE vehicles SET list_price = 19000 WHERE vin = '${VIN}'`);
    const again = await sendAgain(page, saved);
    expect(again.status).toBe(200);
    expect(again.body).toContain(`<template data-target="${saved.templates[0]}">`);
    expect(again.body).toContain('$18,000');
    expect(storedVehicle(VIN)?.listPrice).toBe(19000);
    expect(errors).toEqual([]);
});

test('what is typed while a save is out stays in the field when its answer lands', async ({ page }) => {
    test.setTimeout(60_000);
    const errors = collectFormErrors(page);
    insertVehicle(testVehicle(VIN));
    await signIn(page);
    await page.goto(VEHICLES);
    await ready(page);
    const row = vehicleRow(page, VIN);
    const requests = await recordRequests(page);
    const release = await holdWrites(page);
    await row.locator('a', { hasText: '$17,900' }).click();
    await expectRowAction(requests, 'GET', `${PATH}/row/edit`, ANCHOR, 200);
    const price = page.locator(`#list_price-${ANCHOR}`);

    // While typed into, the field is left out of morphs; Enter takes that off before it sends.
    await page.keyboard.type('1520');
    await expect(price).toHaveAttribute('data-ignore-morph', '');
    await price.press('Enter');
    await expect(row.locator('form')).toHaveAttribute('data-loading', '');
    await expect(price).not.toHaveAttribute('data-ignore-morph');

    // Typing on while the save is out: the field is left out again, so the refusal lands around what was typed.
    await page.keyboard.type('0');
    await expect(price).toHaveAttribute('data-ignore-morph', '');
    release();
    await expectRowAction(requests, 'PUT', `${PATH}/row`, ANCHOR, 422);
    await expect(page.locator(`#error-list_price-${ANCHOR}`)).toHaveText('The list price is below the cost, $15,100.');
    await expect(price).toHaveValue('15200');
    await expect(price).toBeFocused();

    // Enter sends what is there now, and its answer morphs the field: the row comes back shown with it.
    await price.press('Enter');
    await expectRowAction(requests, 'PUT', `${PATH}/row`, ANCHOR, 200);
    await expect(row.locator('input:not([name="vins[]"]), select')).toHaveCount(0);
    await expect(row).toContainText('$15,200');
    expect(storedVehicle(VIN)?.listPrice).toBe(15200);
    expect(errors).toEqual([]);
});
