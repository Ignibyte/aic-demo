# Inventory

The vehicles on Ridgeway Motors' lot, for signed-in staff: listed at `/app/inventory`, and each one's record viewed, added, edited and deleted below it (#5). The module requires `staff`: every page sits under its protected `/app` prefix, so a signed-out visitor goes to `/sign-in?next=…` and comes back, and every page uses its exported `StaffPages` document.

## Data

`CreateVehiclesTable` makes `vehicles`: an identity `id`, `vin` (17 characters, unique), `year`, `make`, `model`, `trim`, `body_style`, `mileage`, `exterior_colour`, `list_price` and `cost` (whole dollars, integers), `status`, `added_on` (a date), `created_at` and `updated_at`. Statuses are `VehicleStatus` cases stored as `in_stock`, `on_hold` and `sold`; `VehicleStatuses::stored()` reads a stored value back and refuses any other, and `tone()` gives each its badge colour. `VehicleStatus::label()`, `VehicleStatuses::tone()` and the seed's `SeedVehicles::status()` choose between enum cases with if/return: elephc cannot lower a `match` over enum cases yet (AIC #164), and it types a ternary between two cases as their backing string, which the native site's build refuses (no AIC ticket carries that second limit yet).

`VehicleRepository` reads for the list and saves for the seed: `save()` inserts a vehicle or updates the one with its VIN, `makes()` lists the makes on the lot, and `count()` and `page()` read what a list's query keeps. `VehicleRecordRepository` reads and writes one vehicle by its VIN for the record's pages: `find()`, `taken()`, `create()`, `update()`, `delete()` and the database's `today()`. Both read rows through `VehicleRows`, every value is a bound parameter, every statement of the second is a fixed constant, and the list's sort column comes from the first's own list.

## The list

`InventoryListPage` answers `GET /app/inventory` with the panel `InventoryList` builds: the theme's `FilterBar`, its `DataTable` of `TableRow`s, 15 vehicles a page, and its `Pagination` past one page. Every control is a plain GET parameter, so the list works without JavaScript; with it, Tessera answers each one in place.

| Parameter | What it does | Default |
|---|---|---|
| `q` | Searches make, model and VIN, ignoring case | none |
| `make` | One make on the lot | every make |
| `status` | `in_stock`, `on_hold` or `sold` | every status |
| `min_price`, `max_price` | List price bounds, whole dollars, 1 to 7 digits | no bound |
| `sort` | `year`, `make`, `model`, `mileage`, `price`, `status` or `added`; `-` before it reverses | `-added`, newest on the lot first |
| `page` | The page, 1 to 9999 | 1 |

`InventoryQueries` checks each parameter on its own: one that is missing, unknown or malformed falls back to its default and leaves the others alone, and a page past the last shows the first. A sortable head links to its column ascending, or to the other direction when it is the sort, with `aria-sort` on it. `InventoryLinks` writes every link with the search and filters kept and only the parameters that differ from their defaults; a sort starts again at page 1. The status cell is the table row's badge in `StatusBadge`'s tone. The list never shows the cost.

The list links each VIN to its vehicle's page and offers "Add a vehicle" above the filters; after a delete it shows a notice saying which vehicle went.

## A vehicle's record

Each vehicle's pages are found by its VIN, not its numeric `id`: gate:23 replays the native steps on the PHP site and then on the compiled one against the same database, and a vehicle made in each pass would get a new id, so its location and every link to it would differ; the VIN the steps post is the same in both. A route's VIN is checked against the VIN alphabet (`[A-HJ-NPR-Z0-9]{17}`) before any query, and one that is malformed or on no vehicle answers 404 (`VehicleRecords`).

| Route | Controller | What it answers |
|---|---|---|
| `GET /app/inventory/{vin}` | `VehiclePage` | The vehicle's `DetailCard`: title, status badge, VIN, year, make, model, trim, body style, colour, mileage, list price and the day it came; never its cost. Edit, Delete and Back to the list, and "Record a sale" when the vehicle is not sold. Under the card, the vehicle's sale when one is recorded (see below). |
| `GET /app/inventory/new` | `VehicleNewPage` | The blank form, in stock and on the lot from the database's today. |
| `POST /app/inventory` | `VehicleCreatePost` | 303 to the new vehicle's page, or the form again as 422. |
| `GET /app/inventory/{vin}/edit` | `VehicleEditPage` | The form filled from the vehicle. |
| `POST /app/inventory/{vin}` | `VehicleUpdatePost` | 303 to the vehicle's page (its new path when the VIN changed), 422, or 400 for the envelope. |
| `GET /app/inventory/{vin}/delete` | `VehicleDeletePage` | The `ConfirmCard`: Delete, or Keep it back to the vehicle. A sold vehicle gets a warning `Notice` and no form. |
| `POST /app/inventory/{vin}/delete` | `VehicleDeletePost` | 303 to the list; 422 for a sold vehicle; 400 for the envelope. |

Every vehicle page is one wide `vehicle` panel in the staff pages' document, framed by `staff`'s `StaffPanels`, which `VehicleServices` builds with that id stem; the 404, 400 and sold pages are its warning page.

The form (`VehicleForm`, its grid from `VehicleFields`) is AIC's `Form` atom around the theme's `FormGrid` of `FormField`s and the status `SelectField`, then `FormActions`. No field is browser-required, so every post reaches `VehicleChecks`:

- the VIN: 17 letters and digits without I, O or Q (taken in capitals), its ISO 3779 check digit right (`Vins::checkDigit()`; the message names the digit it should be), and no other vehicle holding it;
- the year: a model year from 1981 to next year, by the database's date;
- make, model, body style and colour required, trim optional, each within its column's length;
- mileage, list price and cost: whole numbers from 0, one to seven digits;
- the status: one of the three, and sold while a sale is recorded (`VehicleSaves`; void the sale first); the day it came onto the lot: a real `YYYY-MM-DD`.

A refusal answers 422 with the error alert above the fields, each message beside its field, and every value as typed. The create's insert skips a VIN taken since the check (`ON CONFLICT DO NOTHING`), and an update that meets one on the unique key finds it taken; both answer that refusal too; the delete's statement keeps a sold row whatever the page said. A save or a delete leaves one line in the session (`StaffNotices`, the `staff` module's, which customers share) for the page it redirects to, which shows it as a success `Notice` and clears it.

The edit and delete forms carry AIC's signed envelope sealing the vehicle's path (`StaffSeals`, over `EnvelopeCodec`, from `staff` too). A post whose envelope is missing, altered or sealed for another vehicle's path answers 400 and changes nothing, so a form issued for one vehicle cannot change or delete another. The CSRF token and honeypot are the `Form` atom's.

## Sales

The `sales` module requires inventory, so inventory cannot require it back. It exports a plugin type instead, `VehicleSales` (with its value `VehicleSale`), and `sales` registers its implementation. `VehicleServices` takes every plugin with `#[Tagged(VehicleSales::class)]` and hands them to `SoldVehicles`, which asks them for a vehicle's sale and for the path of the form that records one; the generated provider builds a plugin with no arguments, so the database comes with each call. With a sale, the vehicle's page shows a "Sale" table (`vehicle-sale`: the day linked to the sale, the customer linked to theirs, the salesperson and the price), and the edit form refuses any status but sold. Without one, a vehicle that is not sold gets "Record a sale" (`vehicle-sell`), which opens `/app/sales/new?vehicle={VIN}`. Without the `sales` module, the page shows neither.

`VehicleStatusRepository`, exported, is how `sales` changes a vehicle: it locks a vehicle's row while a sale is recorded and sets its status (sold, or in stock when a sale is voided), on the caller's transaction. A delete that meets a sale's foreign key, because a sale landed after an edit's check, answers the delete page as 422 (`VehicleDeletes`). Inventory also exports what the sales seed builds its sold vehicles with: `Vehicle`, `VehiclePrice`, `VehicleStatus`, `Vins`, `VehicleRepository` and `SeedVehicles`.

## Seed

`bin/aic inventory:seed` takes no arguments (exit 2 with its usage otherwise) and saves 60 invented vehicles, five each of twelve makes (`SeedVehicles`). The same 60 come out every run, so a rerun updates the same rows by VIN. Each VIN (`Vins`) starts with `0LT`, a world manufacturer identifier no region is assigned, and carries the model-year code and a valid ISO 3779 check digit, so it is well formed and can belong to no real vehicle.

Run `bin/aic migrate` first. From the host, point both at DDEV's published Postgres port (`PGPORT`, from `ddev describe`), or run them with `ddev exec`.

## Checks

`config/native-site.json` asks for the list signed out (303), signs in again, then asks for it plain, filtered, sorted, at page 2 and with malformed parameters. Then it walks a vehicle's record with the fictional VIN `0LTGATE19SR999001`: the new form, a create refused on every field, on its check digit and on a taken VIN (422 each) and with no token (403), the create (303), its page and edit form, an update with an altered envelope (400), a refused update (422), an update to sold, the sold vehicle's delete page and its refused delete (422), back in stock, the delete page and the delete (303), the list with its notice, and 404 for the deleted vehicle's page and edit form and for a malformed VIN. The steps delete the vehicle they make, so the compiled site's pass finds the database as the PHP site's did; a run stopped half way leaves it, and the next create then answers 422 until it is deleted.

The Playwright tests `tests/browser/inventory-*.spec.ts` compare what the list shows with what the same query gives from Postgres: `tests/browser/support/inventory.ts` runs `psql` with the `PGHOST` and `PGPORT` that gate:23 and `vendor/bin/aic-gate browser` give the tests, so the seed must have run against that database. `tests/browser/vehicle-*.spec.ts` add, refuse, edit, delete and fail to delete a sold vehicle in the browser, each on its own `0LTPW…` VINs, which `tests/browser/support/vehicles.ts` deletes before and after each test and reads back through `psql`.
