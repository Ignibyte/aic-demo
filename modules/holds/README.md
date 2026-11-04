# Holds

A vehicle in stock kept for a customer for 48 hours (#20): off the public site, and sold to no one else. The module requires `staff` (who is signed in, the users, the staff pages' frame), `inventory` (the vehicles, their status through `VehicleStatusRepository`) and `customers`. Every page sits under `/app`, which staff's protected prefix keeps to signed-in staff. `sales` requires this module, never the other way round.

## Data

`CreateHoldsTable` makes `holds`: an identity `id`; `vehicle_id` (to `vehicles`, cascade, so a deleted vehicle takes its holds); `customer_id` and `placed_by` (to `customers` and `users`, set null, so a hold outlives a deleted customer or member of staff); `note`; `status`, a `HoldStatus` value (`active`, `released`, `sold`); `created_at`, when it was placed; and `updated_at`. It is indexed on `status` and `vehicle_id`.

`CreateHoldsView` makes `hold_states`, the one place the module writes SQL: each hold joined to its car (title and VIN), its customer's name and who placed it, with `placed_on` (the day, UTC), `expires` (48 hours after it was placed, `YYYY-MM-DD HH:MM` UTC) and `expired`, whether that time has passed by the database's `now()`. No time is worked out in PHP. `HoldTable` holds both entity types; `HoldRows` reads a view row into a `Hold`.

`HoldRepository`, exported, reads and writes through AIC's entity store:

- `place()` runs one transaction that locks the vehicle's row through inventory's `VehicleStatusRepository::lockedStatus()`, gives up unless it is `in_stock`, inserts the hold and marks the vehicle `on_hold`. A sale locks the same row, so a hold and a sale of one vehicle cannot pass together.
- `active()` lists the active holds, newest first; `expired()` those whose time has passed, oldest first; `find()` one by id.
- `release()` runs one transaction per hold: it locks the vehicle, then the hold while it is still active, marks it `released`, puts the vehicle back `in_stock` while it is still `on_hold` (a status staff changed by hand stands), and queues the notification inside the same transaction, so a hold is released and told of once.
- `heldForAnother()` and `endForSale()` are sales': whether a vehicle's active hold is for someone else, and the hold ended as `sold` when its vehicle sells to its customer. Both run on the caller's transaction, after the vehicle's row is locked.

A hold whose customer was deleted has none, so the vehicle sells to no one until the hold is released.

For a vehicle's page (#31) `HoldRepository` also gives `forVehicle()`, the vehicle's newest holds from `hold_states` by its VIN whatever their status, each a `HoldTrace` (exported) with its state as staff read it (Active, Expired once its time passed, Released or Sold; `HoldRows::trace()`), and `version()`, the vehicle's holds' count, newest `updated_at` and how many active ones passed their time. The `panels` module's leads and holds panel reads them.

## Pages

| Route | Controller | What it answers |
|---|---|---|
| `GET /app/holds` | `HoldListPage` | The active holds, newest first: the car linked to its inventory page, the customer, who placed it, the day, when it expires and the note; a "Put a vehicle on hold" link; the notice a placement left. |
| `GET /app/holds/new` | `HoldNewPage` | The form: a vehicle in stock (`?vehicle={VIN}` chooses one in stock), a customer by email and a note, in AIC's `Form` with its token and honeypot. |
| `POST /app/holds` | `HoldCreatePost` | 303 to `/app/holds` with "Put the {car} on hold for {customer} for 48 hours."; 422 with each error beside its field (`HoldChecks`: choose a vehicle on the lot, "This vehicle is not for sale now." for one held or sold, choose a customer on the list, a note of up to 1,000 bytes); 403 without the token. |

The form's flows are `HoldSaves`, which the module's factory `HoldServices` builds for both routes; the member who places a hold is the one signed in. The sidebar's "Holds" link is `site`'s `NavigationBlock`. The public side needs no change: it shows only vehicles `in_stock`, so a held car leaves `/cars`, the feed and its page (404), and its lead posts answer 404.

## The release job

`release-holds` (`ReleaseHolds`) is queued by the `release-holds` schedule every 15 minutes (`*/15 * * * *`, UTC) and releases each expired hold. The member who placed it gets a notification through AIC's `Notifier` (`HoldNotices`): kept in the database and mailed, or kept alone when their login is not an address (the gate's own user, `gate`); it names the car and the customer and links `/app/inventory/{vin}`. A hold whose placer is gone tells no one.

## Checks

`config/native-site.json` holds the sales steps' car, `0LTGATE2XSR999002`, for `ada.abbott@example.test` between "sale form" and the first sale post: its public page in stock and held (404), the list, the form plain and from the vehicle, a placement refused on every field, without a token (403), placed, placed again (422), the sale form and a sale to another customer refused (422). The existing "sale create" then sells it to Ada, which ends the hold, and `/app/holds` follows the sale page. No step reads a page showing the gate's hold while it is active, since the two passes place it at different times; the notice names no time.

The looks run the schedule and the worker on the host (`tests/browser/support/schedules.ts`, `$AIC_PHP bin/aic schedule:run <minute>` on a minute no one ran before, and `queue:work`, AIC #225) and make their own staff and vehicles (`tests/browser/support/holds.ts`). `tests/browser/holds-release.spec.ts` holds a car through the form, sees it leave the public site and a sale to another customer refused, then backdates the hold past its 48 hours and sees the release put it back and tell its placer.
