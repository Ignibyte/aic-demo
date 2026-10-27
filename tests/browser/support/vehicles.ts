import { expect, type Page } from '@playwright/test';
import { literal, signIn, sql } from './inventory';

// The vehicle looks' own VINs: fictional (`0LT`, a maker no region is assigned), check digits worked out by hand, and
// `0LTPW` so no seeded vehicle and no native step shares them.
export const VINS = {
    added: '0LTPWADD0SR999101',
    refused: '0LTPWBADXSR999102',
    refusedWrongDigit: '0LTPWBAD0SR999102',
    edited: '0LTPWEDT6SR999103',
    other: '0LTPWEDT9SR999113',
    deleted: '0LTPWDEL7SR999104',
    sold: '0LTPWSLD9SR999105',
};

// A vehicle as the looks put it in the database and read it back.
export interface TestVehicle {
    vin: string;
    year: number;
    make: string;
    model: string;
    trim: string;
    bodyStyle: string;
    mileage: number;
    colour: string;
    listPrice: number;
    cost: number;
    status: 'in_stock' | 'on_hold' | 'sold';
    addedOn: string;
}

// A test vehicle with the given VIN and status; the rest is fixed.
export function testVehicle(vin: string, status: TestVehicle['status'] = 'in_stock'): TestVehicle {
    return {
        vin,
        year: 2023,
        make: 'Probe',
        model: 'Hatch',
        trim: 'SE',
        bodyStyle: 'Hatchback',
        mileage: 5000,
        colour: 'Slate',
        listPrice: 17900,
        cost: 15100,
        status,
        addedOn: '2026-09-15',
    };
}

// Deletes the vehicles with these VINs, so a look starts and ends with none of its own.
export function removeVins(...vins: string[]): void {
    sql(`DELETE FROM vehicles WHERE vin IN (${vins.map(literal).join(', ')})`);
}

// Puts a vehicle in the database, as the seed would.
export function insertVehicle(vehicle: TestVehicle): void {
    const values = [
        literal(vehicle.vin), vehicle.year, literal(vehicle.make), literal(vehicle.model), literal(vehicle.trim),
        literal(vehicle.bodyStyle), vehicle.mileage, literal(vehicle.colour), vehicle.listPrice, vehicle.cost,
        literal(vehicle.status), literal(vehicle.addedOn),
    ];
    sql(
        'INSERT INTO vehicles (vin, year, make, model, trim, body_style, mileage, exterior_colour, list_price, cost, status, '
            + `added_on, created_at, updated_at) VALUES (${values.join(', ')}, now(), now())`,
    );
}

// The vehicle the database holds with this VIN; null when it holds none.
export function storedVehicle(vin: string): TestVehicle | null {
    const rows = sql(
        "SELECT vin, year, make, model, trim, body_style, mileage, exterior_colour, list_price, cost, status, to_char(added_on, 'YYYY-MM-DD') "
            + `FROM vehicles WHERE vin = ${literal(vin)}`,
    );

    if (rows.length === 0) {
        return null;
    }

    const [storedVin, year, make, model, trim, bodyStyle, mileage, colour, listPrice, cost, status, addedOn] = rows[0].split('|');

    return {
        vin: storedVin,
        year: Number(year),
        make,
        model,
        trim,
        bodyStyle,
        mileage: Number(mileage),
        colour,
        listPrice: Number(listPrice),
        cost: Number(cost),
        status: status as TestVehicle['status'],
        addedOn,
    };
}

// Signs the gate's user in and opens a path.
export async function signInAt(page: Page, path: string): Promise<void> {
    await signIn(page);
    await page.goto(path);
}

// Fills the vehicle form with a vehicle's values.
export async function fillVehicleForm(page: Page, vehicle: TestVehicle): Promise<void> {
    await page.locator('#vehicle-vin').fill(vehicle.vin);
    await page.locator('#vehicle-year').fill(String(vehicle.year));
    await page.locator('#vehicle-make').fill(vehicle.make);
    await page.locator('#vehicle-model').fill(vehicle.model);
    await page.locator('#vehicle-trim').fill(vehicle.trim);
    await page.locator('#vehicle-body_style').fill(vehicle.bodyStyle);
    await page.locator('#vehicle-exterior_colour').fill(vehicle.colour);
    await page.locator('#vehicle-mileage').fill(String(vehicle.mileage));
    await page.locator('#vehicle-list_price').fill(String(vehicle.listPrice));
    await page.locator('#vehicle-cost').fill(String(vehicle.cost));
    await page.locator('#vehicle-status').selectOption(vehicle.status);
    await page.locator('#vehicle-added_on').fill(vehicle.addedOn);
}

// The facts the vehicle's card shows, in order: its definition list is the page's only one.
export async function expectFacts(page: Page, facts: string[]): Promise<void> {
    await expect(page.locator('dd')).toHaveText(facts);
}
