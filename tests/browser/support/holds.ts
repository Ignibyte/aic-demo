import { literal, sql } from './inventory';
import { insertStaff, mailFor, removeMail } from './leads';

// Every member of staff and lead the holds and reports looks make ends here, and every vehicle's VIN starts `0LTPWH`
// (fictional, as the vehicle looks' are), so their holds, notifications and mail are theirs alone and go afterwards.
export const DOMAIN = '@holds-look.test';

// The looks' own VINs.
export const VINS = {
    held: '0LTPWHLD1SR999201',
    aged61: '0LTPWHAG1SR999211',
    aged60: '0LTPWHAG2SR999212',
    aged75: '0LTPWHAG3SR999213',
    sold90: '0LTPWHAG4SR999214',
    today: '0LTPWHDG5SR999221',
};

// The subjects of the reports' notifications and mails, as text Mailpit's search and SQL's LIKE find.
const REPORTS = ['vehicles on the lot more than', 'vehicle on the lot more than', 'Ridgeway Motors on', 'The hold on the'];

// A member of staff of the looks' own.
export interface Member {
    id: string;
    email: string;
    password: string;
    name: string;
}

// Makes a member of staff with that role and a fresh password.
export function insertMember(name: string, role: string): Member {
    const email = `${name.toLowerCase().replaceAll(' ', '.')}.${Date.now()}${DOMAIN}`;
    const password = insertStaff(email, name, role);
    const [id] = sql(`SELECT id FROM users WHERE email = ${literal(email)}`);

    return { id, email, password, name };
}

// Puts a vehicle of the looks' own on the lot `daysAgo` days before the database's today, with that status.
export function insertLookVehicle(vin: string, status: string, daysAgo: number, model = 'Hatch'): number {
    const [id] = sql(
        'INSERT INTO vehicles (vin, year, make, model, trim, body_style, mileage, exterior_colour, list_price, cost, status, '
            + `added_on, created_at, updated_at) VALUES (${literal(vin)}, 2023, 'Probe', ${literal(model)}, 'SE', 'Hatchback', 5000, `
            + `'Slate', 17900, 15100, ${literal(status)}, current_date - ${daysAgo}, now(), now()) RETURNING id`,
    );

    return Number(id);
}

// The database's now, which a look's clean-up counts from.
export function databaseNow(): string {
    return sql('SELECT now()::text')[0];
}

// The status of a vehicle by its VIN.
export function vehicleStatus(vin: string): string {
    return sql(`SELECT status FROM vehicles WHERE vin = ${literal(vin)}`)[0] ?? '';
}

// One hold of a vehicle, as the looks check it.
export interface StoredHold {
    id: number;
    status: string;
    customer: string;
    placedBy: string;
    note: string;
    expires: string;
    hoursLeft: number;
}

// The holds of a vehicle, oldest first.
export function storedHolds(vin: string): StoredHold[] {
    return sql(
        "SELECT h.id || '|' || h.status || '|' || coalesce(c.email, '') || '|' || coalesce(u.email, '') || '|' || h.note || '|' "
            + "|| to_char((h.created_at + interval '48 hours') AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI') || '|' "
            + "|| round(extract(epoch FROM (h.created_at + interval '48 hours' - now())) / 3600) "
            + 'FROM holds h JOIN vehicles v ON v.id = h.vehicle_id LEFT JOIN customers c ON c.id = h.customer_id '
            + `LEFT JOIN users u ON u.id = h.placed_by WHERE v.vin = ${literal(vin)} ORDER BY h.id`,
    ).map((row) => {
        const [id, status, customer, placedBy, note, expires, hoursLeft] = row.split('|');

        return { id: Number(id), status, customer, placedBy, note, expires, hoursLeft: Number(hoursLeft) };
    });
}

// The subjects, bodies and links of a member's stored notifications, oldest first.
export function notificationsOf(member: Member): { subject: string; body: string; link: string }[] {
    return sql(
        "SELECT subject || '|' || replace(body, E'\\n', '~') || '|' || coalesce(link, '') FROM aic_notifications "
            + `WHERE user_id = ${literal(member.id)} ORDER BY id`,
    ).map((row) => {
        const [subject, body, link] = row.split('|');

        return { subject, body: body.replaceAll('~', '\n'), link };
    });
}

// Deletes the looks' vehicles (their holds, sales and leads go first or with them), their staff and notifications,
// the reports' notifications, mails and failed deliveries others got since `since`, and the looks' mail.
export async function removeHoldLooks(since = ''): Promise<void> {
    sql("DELETE FROM sales WHERE vehicle_id IN (SELECT id FROM vehicles WHERE vin LIKE '0LTPWH%')");
    sql(`DELETE FROM leads WHERE email LIKE '%${DOMAIN}'`);
    sql("DELETE FROM vehicles WHERE vin LIKE '0LTPWH%'");
    sql(`DELETE FROM aic_notifications WHERE user_id IN (SELECT id::text FROM users WHERE email LIKE '%${DOMAIN}')`);
    sql(`DELETE FROM users WHERE email LIKE '%${DOMAIN}'`);

    if (since !== '') {
        const subjects = REPORTS.map((subject) => `subject LIKE ${literal(`%${subject}%`)}`).join(' OR ');
        sql(`DELETE FROM aic_notifications WHERE created_at >= ${literal(since)} AND (${subjects})`);
        sql(`DELETE FROM aic_failed_jobs WHERE failed_at >= ${literal(since)} AND payload LIKE '%Ridgeway Motors on%'`);
    }

    for (const text of [DOMAIN, ...REPORTS]) {
        await removeMail(await mailFor(text));
    }
}
