# Inventory

The vehicles on Ridgeway Motors' lot, listed for signed-in staff at `/app/inventory`. This is the first half of inventory; creating, editing and deleting a vehicle comes next. The module requires `staff`: the list sits under its protected `/app` prefix, so a signed-out visitor goes to `/sign-in?next=%2Fapp%2Finventory`, and the page uses its exported `StaffPages` document.

## Data

`CreateVehiclesTable` makes `vehicles`: an identity `id`, `vin` (17 characters, unique), `year`, `make`, `model`, `trim`, `body_style`, `mileage`, `exterior_colour`, `list_price` and `cost` (whole dollars, integers), `status`, `added_on` (a date), `created_at` and `updated_at`. Statuses are `VehicleStatus` cases stored as `in_stock`, `on_hold` and `sold`; `VehicleStatuses::stored()` reads a stored value back and refuses any other, and `tone()` gives each its badge colour. `VehicleStatus::label()`, `VehicleStatuses::tone()` and the seed's `SeedVehicles::status()` choose between enum cases with if/return: elephc cannot lower a `match` over enum cases yet (AIC #164), and it types a ternary between two cases as their backing string, which the native site's build refuses (no AIC ticket carries that second limit yet).

`VehicleRepository` is the one reader and writer: `save()` inserts a vehicle or updates the one with its VIN, `makes()` lists the makes on the lot, and `count()` and `page()` read what a list's query keeps. Every value is a bound parameter, and the sort's column comes from the repository's own list.

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

## Seed

`bin/aic inventory:seed` takes no arguments (exit 2 with its usage otherwise) and saves 60 invented vehicles, five each of twelve makes (`SeedVehicles`). The same 60 come out every run, so a rerun updates the same rows by VIN. Each VIN (`Vins`) starts with `0LT`, a world manufacturer identifier no region is assigned, and carries the model-year code and a valid ISO 3779 check digit, so it is well formed and can belong to no real vehicle.

Run `bin/aic migrate` first. From the host, point both at DDEV's published Postgres port (`PGPORT`, from `ddev describe`), or run them with `ddev exec`.

## Checks

`config/native-site.json` asks for the list signed out (303), signs in again, then asks for it plain, filtered, sorted, at page 2 and with malformed parameters. The Playwright tests `tests/browser/inventory-*.spec.ts` compare what the list shows with what the same query gives from Postgres: `tests/browser/support/inventory.ts` runs `psql` with the `PGHOST` and `PGPORT` that gate:23 and `vendor/bin/aic-gate browser` give the tests, so the seed must have run against that database.
