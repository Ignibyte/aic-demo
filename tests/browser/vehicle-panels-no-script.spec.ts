// With JavaScript off the vehicle page shows its three panels inline (#31): a plain request gets each panel built,
// with no skeleton, and a panel's own route loads its page whole.

import { expect, test } from '@playwright/test';
import { arrived, insertPanelVehicle, insertPriceChange, PANELS, removePanelLooks, skeleton, vin } from './support/panels';
import { signInAt } from './support/vehicles';

const VIN = vin('0LTPNNSC2SR31401');
const PATH = `/app/inventory/${VIN}`;

test.use({ javaScriptEnabled: false });

test.beforeEach(() => {
    removePanelLooks();
    insertPanelVehicle({ vin: VIN, listPrice: 30000 });
    insertPanelVehicle({ vin: vin('0LTPNNSC2SR31402'), listPrice: 31000 });
    insertPriceChange(VIN, 31500, 30000, '2026-10-02');
});
test.afterEach(() => removePanelLooks());

test('with JavaScript off the panels render inline, and a panel route loads whole', async ({ page }) => {
    await signInAt(page, PATH);
    await expect(page.locator('#vehicle-title')).toBeVisible();

    for (const panel of PANELS) {
        await expect(arrived(page, panel.key, VIN)).toBeVisible();
        await expect(arrived(page, panel.key, VIN).getByRole('heading', { level: 3 })).toHaveText(panel.heading);
        await expect(skeleton(page, panel.key, VIN)).toHaveCount(0);
        await expect(arrived(page, panel.key, VIN).locator('.skeleton')).toHaveCount(0);
    }

    await expect(arrived(page, 'price-history', VIN).locator('li').first()).toHaveText('2026-09-01 $31,500');
    await expect(arrived(page, 'similar', VIN)).toContainText(vin('0LTPNNSC2SR31402'));
    await expect(arrived(page, 'activity', VIN)).toContainText('No leads on this vehicle.');

    await page.goto(`${PATH}/similar`);
    await expect(page).toHaveTitle('2024 Panelcar Coupe GT · Lotline');
    await expect(arrived(page, 'similar', VIN)).toContainText(vin('0LTPNNSC2SR31402'));
    await expect(arrived(page, 'price-history', VIN)).toHaveCount(0);
});
