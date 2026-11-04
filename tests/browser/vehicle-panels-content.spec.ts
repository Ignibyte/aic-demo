// What each of the vehicle page's panels shows (#31): the price line from the day the vehicle came to today, through
// each recorded change; the four vehicles nearest its price that are not sold, of its make and body style; and its
// leads and holds, newest first. A vehicle with none of them says so in each.

import { expect, test } from '@playwright/test';
import { sql } from './support/inventory';
import { insertStaff } from './support/leads';
import {
    arrived, DOMAIN, insertHold, insertLead, insertPanelVehicle, insertPriceChange, removePanelLooks, vin,
} from './support/panels';
import { signInAt } from './support/vehicles';

const VIN = vin('0LTPNCNT2SR31101');
const LONE = vin('0LTPNCNT2SR31109');
const NEAR = {
    same: vin('0LTPNCNT2SR31102'),
    below: vin('0LTPNCNT2SR31103'),
    above: vin('0LTPNCNT2SR31104'),
    far: vin('0LTPNCNT2SR31105'),
    farther: vin('0LTPNCNT2SR31106'),
    sold: vin('0LTPNCNT2SR31107'),
    sedan: vin('0LTPNCNT2SR31108'),
};

test.beforeEach(() => {
    removePanelLooks();
    insertPanelVehicle({ vin: VIN, listPrice: 30000, addedOn: '2026-09-01' });
    insertPanelVehicle({ vin: NEAR.same, listPrice: 30000 });
    insertPanelVehicle({ vin: NEAR.below, listPrice: 29500 });
    insertPanelVehicle({ vin: NEAR.above, listPrice: 30800 });
    insertPanelVehicle({ vin: NEAR.far, listPrice: 28000 });
    insertPanelVehicle({ vin: NEAR.farther, listPrice: 33000 });
    insertPanelVehicle({ vin: NEAR.sold, listPrice: 30100, status: 'sold' });
    insertPanelVehicle({ vin: NEAR.sedan, listPrice: 30050, bodyStyle: 'Sedan' });
    insertPanelVehicle({ vin: LONE, listPrice: 9000, bodyStyle: 'Wagon' });
    insertPriceChange(VIN, 32000, 31000, '2026-09-20');
    insertPriceChange(VIN, 31000, 30000, '2026-10-10');
});
test.afterEach(() => removePanelLooks());

test('each panel shows its own data, nearest and newest first', async ({ page }) => {
    insertStaff(`ann.panel${DOMAIN}`, 'Ann Panel', 'salesperson');
    const [annId] = sql(`SELECT id FROM users WHERE email = 'ann.panel${DOMAIN}'`);
    insertLead(VIN, 'Older Visitor', 'contacted', 60, annId);
    insertLead(VIN, 'Newer Visitor', 'new', 10);
    insertHold(VIN, 'released', 100, annId);
    insertHold(VIN, 'active', 60, annId);
    insertHold(VIN, 'active', 2, annId);
    const [today] = sql("SELECT to_char(current_date, 'YYYY-MM-DD')");

    await signInAt(page, `/app/inventory/${VIN}`);

    // The line: the day it came at the first change's old price, each change, then today at its price now; the
    // highest price at the top of the drawing and the lowest at the bottom.
    const line = arrived(page, 'price-history', VIN).locator(`#price-line-${VIN}`);
    await expect(line.locator('svg[role="img"] title').first()).toHaveText('The list price of the 2024 Panelcar Coupe GT over time');
    await expect(line.locator('polyline')).toHaveCount(1);
    await expect(line.locator('circle')).toHaveCount(4);
    await expect(line.locator('li')).toHaveText(['2026-09-01 $32,000', '2026-09-20 $31,000', '2026-10-10 $30,000', `${today} $30,000`]);
    await expect(line.locator('circle').first()).toHaveAttribute('cy', '12');
    await expect(line.locator('circle').nth(1)).toHaveAttribute('cy', '60');
    await expect(line.locator('circle').nth(2)).toHaveAttribute('cy', '108');
    await expect(line.locator('circle').nth(3)).toHaveAttribute('cy', '108');
    const points = await line.locator('polyline').getAttribute('points');
    expect(points?.split(' ')).toHaveLength(4);
    await expect(line.locator(`#price-line-${VIN}-empty`)).toHaveCount(0);

    // The similar vehicles: the four nearest its price, not sold, of its make and body; not itself.
    const similar = arrived(page, 'similar', VIN).locator('tbody tr td:first-child');
    await expect(similar).toHaveText([NEAR.same, NEAR.below, NEAR.above, NEAR.far]);
    await expect(arrived(page, 'similar', VIN).getByRole('link', { name: NEAR.same })).toHaveAttribute('href', `/app/inventory/${NEAR.same}`);

    // The leads, newest first, with their stage and salesperson; the holds, newest first, with their state.
    const leads = arrived(page, 'activity', VIN).locator(`#activity-${VIN}-leads tbody tr`);
    await expect(leads).toHaveCount(2);
    await expect(leads.first().locator('td')).toHaveText([/^\d{4}-\d{2}-\d{2}$/, 'Newer Visitor', 'Question', 'New', 'Unassigned']);
    await expect(leads.nth(1).locator('td')).toHaveText([/^\d{4}-\d{2}-\d{2}$/, 'Older Visitor', 'Question', 'Contacted', 'Ann Panel']);
    await expect(leads.first().getByRole('link', { name: 'Newer Visitor' })).toHaveAttribute('href', /^\/app\/leads\/\d+$/);
    const holds = arrived(page, 'activity', VIN).locator(`#activity-${VIN}-holds tbody tr`);
    await expect(holds.locator('td:nth-child(4)')).toHaveText(['Active', 'Expired', 'Released']);
    await expect(holds.first().locator('td:nth-child(3)')).toHaveText('Ann Panel');
});

test('a vehicle with no changes, no like vehicle, no lead and no hold says so in each panel', async ({ page }) => {
    await signInAt(page, `/app/inventory/${LONE}`);
    const line = arrived(page, 'price-history', LONE).locator(`#price-line-${LONE}`);
    await expect(line.locator('circle')).toHaveCount(2);
    await expect(line.locator('circle').first()).toHaveAttribute('cy', '60');
    await expect(line.locator(`#price-line-${LONE}-empty`)).toHaveText('No price changes recorded.');
    await expect(arrived(page, 'similar', LONE)).toContainText('No similar vehicles on the lot.');
    await expect(arrived(page, 'activity', LONE)).toContainText('No leads on this vehicle.');
    await expect(arrived(page, 'activity', LONE)).toContainText('No holds on this vehicle.');
});
