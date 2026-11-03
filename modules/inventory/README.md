# Inventory

The vehicles on Ridgeway Motors' lot, for signed-in staff: listed at `/app/inventory`, and each one's record viewed, added, edited and deleted below it (#5), with its photos (#16). The module requires `staff`, `photos` and `finance` (#26), whose payment calculator each vehicle's page shows: every page sits under its protected `/app` prefix, so a signed-out visitor goes to `/sign-in?next=…` and comes back, and every page uses its exported `StaffPages` document.

## Data

`CreateVehiclesTable` makes `vehicles`: an identity `id`, `vin` (17 characters, unique), `year`, `make`, `model`, `trim`, `body_style`, `mileage`, `exterior_colour`, `list_price` and `cost` (whole dollars, integers), `status`, `added_on` (a date), `created_at` and `updated_at`. Statuses are `VehicleStatus` cases stored as `in_stock`, `on_hold` and `sold`; `VehicleStatuses::stored()` reads a stored value back and refuses any other, and `tone()` gives each its badge colour. `VehicleStatus::label()`, `VehicleStatuses::tone()` and the seed's `SeedVehicles::status()` choose between enum cases with if/return: elephc cannot lower a `match` over enum cases yet (AIC #164), and it types a ternary between two cases as their backing string, which the native site's build refuses (no AIC ticket carries that second limit yet).

`VehicleTable::type()` is the table's AIC entity type, the one place it is named (#14, AIC #185); it is exported, so `sales` references a vehicle and reads its choices through it and the dashboard counts it. Every repository reads and writes through AIC's `EntityStore` and `EntityQuery`, never the raw database (gate:9). `VehicleRepository` reads for the list and saves for the seed: `save()` upserts on the VIN, `makes()` lists the makes on the lot (the field's distinct values), and `count()` and `page()` read what a list's query keeps, its search over make, model and VIN and its filters on one query. `VehicleRecordRepository` reads and writes one vehicle by its VIN for the record's pages: `find()`, `taken()`, `create()` (an insert that skips a taken VIN), `update()` and `delete()` (changes by query, the delete keeping a sold row) and the database's `today()`. Both read rows through `VehicleRows`, which also gives a vehicle's values by field, and the list's sort field comes from the first's own list.

The database's today is a view: `CreateTodayView` makes `inventory_today` (`SELECT current_date AS today`), and `TodayRepository`, exported, reads it through a read-only entity type. The entity API has no query for the date, and a view is where a module writes the SQL it needs; the vehicle form, the sale form and the dashboard's version all count from it, so the date stays the database's.

The public side reads the vehicles for sale through three exports (#17), which the `storefront` module uses. `ListedVehicleRepository` reads only rows whose status is `in_stock`: the makes and the model years for sale, the count and a page of what a `ListingQuery` keeps (a make, a year, a price range and a sort: `newest`, by `added_on` descending, or `price` and `-price`, by `list_price`; each then by VIN), every vehicle of a make for the feed, and the one of a year with a key. It reads each row as a `ListedVehicle`: the row's id, which its photos refer to, what it is, its mileage and list price, and its key, the first 6 hex digits of the SHA-256 of its VIN, which the public slug ends with. A `ListedVehicle` has no VIN and no cost, so nothing outside this module can show them; the key is found by reading the year's rows and comparing it in PHP, since the entity API has no hash.

## The list

`InventoryListPage` answers `GET /app/inventory` with the panel `InventoryList` builds: the theme's `FilterBar`, then the results, keyed `table`: a `TableResults` holding the kept sort, the `DataTable` of `TableRow`s, 15 vehicles a page, and its `Pagination` past one page. The results are an AIC `SourcedComponent` (#14, AIC #192): `InventoryResults` holds the repository and the parsed query and counts, pages and reads the vehicles in `build()`, which the render pass calls only when it renders them, so a Tessera answer for anything else never reads them. `InventoryTable::results()` lays them out. Every control is a plain GET parameter, so the list works without JavaScript.

With JavaScript, a filter, a sort and a page each ask Tessera for the results alone: the filter form, each sortable head's link and each page link carry `data-target="@inventory-results"`. That is the results' anchor (AIC #188, `InventoryTable::ANCHOR`): `InventoryList` puts it on the panel's part `table`, AIC prints it as `data-t-anchor` on the results' root element, and Tessera sends the path it finds there, so no code here spells the path and a wrapper or a reorder of the page cannot strand it. The theme's `TableResults` holds them in one element, which Tessera needs (protocol 1 and 2), since a component is marked on its one root element. The answer is one fragment, the sidebar, header and filter bar stay as they are, and Tessera pushes the URL, so back and forward ask for the results again. The filter bar's "Clear" link refreshes the whole `content` region, so it clears the fields too. The sort the filter form sends is a theme `FormValue` inside the results, a hidden `sort` field tied to the form by `form="inventory-filters"`, so it changes with the table on a sort; the form holds no copy of its own, and a plain GET sends it last, as before.

| Parameter | What it does | Default |
|---|---|---|
| `q` | Searches make, model and VIN, ignoring case | none |
| `make` | One make on the lot | every make |
| `status` | `in_stock`, `on_hold` or `sold` | every status |
| `min_price`, `max_price` | List price bounds, whole dollars, 1 to 7 digits | no bound |
| `sort` | `year`, `make`, `model`, `mileage`, `price`, `status` or `added`; `-` before it reverses | `-added`, newest on the lot first |
| `page` | The page, 1 to 9999 | 1 |

`InventoryQueries` checks each parameter on its own: one that is missing, unknown or malformed falls back to its default and leaves the others alone, and a page past the last shows the first. A sortable head links to its column ascending, or to the other direction when it is the sort, with `aria-sort` on it. `InventoryLinks` writes every link with the search and filters kept and only the parameters that differ from their defaults; a sort starts again at page 1. The status cell is the table row's badge in `StatusBadge`'s tone. The list never shows the cost.

### Search as you type

The search box is the theme's `SearchField` (#27), in the filter form with the id `filter-q` and the name `q`. Each pause of 300 ms in typing submits the form (`data-on:input__debounce.300ms="el.form.requestSubmit()"`), and Tessera sends that as the same visit a Filter click makes: the results alone, by `@inventory-results`, with the query in the URL, so a reload shows the same list. There is no `@get`: an action never touches history in Tessera, so it could not keep `?q=`. The form carries `data-push="replace"` (Tessera #37), so each pause replaces the list's history entry rather than adding one; the Filter button carries `data-push="push"`, which overrides the form's, so a pressed Filter still adds an entry, and a sort or a page link adds one as before. The box sits outside the results, so their answer leaves its focus and caret alone, and `data-focus-key="inventory-search"` names it for Back and Forward. On Back and Forward the list, its count and the URL follow the entry, and the box keeps the text last typed, since no answer redraws it. While the request is out Tessera marks the form `data-loading`, which shows the box's spinner, and the results `aria-busy="true"`, which dims them. A newer search supersedes one still in flight, and a late answer is dropped (Tessera #38). With JavaScript off the form is the plain GET form with its Filter button.

The search reads through `EntityQuery::search()`: an `ILIKE '%text%'` for each of make, model and VIN, joined by `OR`, with `%` and `_` matched as typed. `whereAny()` does not apply, since a `Comparison` is `=`, `<>`, `<=` or `>=` and none matches part of a value.

The list links each VIN to its vehicle's page and offers "Add a vehicle" above the filters; after a delete it shows a notice saying which vehicle went.

## A vehicle's record

Each vehicle's pages are found by its VIN, not its numeric `id`: gate:23 replays the native steps on the PHP site and then on the compiled one against the same database, and a vehicle made in each pass would get a new id, so its location and every link to it would differ; the VIN the steps post is the same in both. A route's VIN is checked against the VIN alphabet (`[A-HJ-NPR-Z0-9]{17}`) before any query, and one that is malformed or on no vehicle answers 404 (`VehicleRecords`).

| Route | Controller | What it answers |
|---|---|---|
| `GET /app/inventory/{vin}` | `VehiclePage` | The vehicle's `DetailCard`: title, status badge, VIN, year, make, model, trim, body style, colour, mileage, list price and the day it came; never its cost. Edit, Delete and Back to the list, and "Record a sale" when the vehicle is not sold. Under the card, finance's payment calculator at the list price (#26), then the photos and the vehicle's sale when one is recorded (see below). |
| `GET /app/inventory/new` | `VehicleNewPage` | The blank form, in stock and on the lot from the database's today. |
| `POST /app/inventory` | `VehicleCreatePost` | 303 to the new vehicle's page, or the form again as 422. |
| `GET /app/inventory/{vin}/edit` | `VehicleEditPage` | The form filled from the vehicle. |
| `POST /app/inventory/{vin}` | `VehicleUpdatePost` | 303 to the vehicle's page (its new path when the VIN changed), 422, or 400 for the envelope. |
| `GET /app/inventory/{vin}/delete` | `VehicleDeletePage` | The `ConfirmCard`: Delete, or Keep it back to the vehicle. A sold vehicle gets a warning `Notice` and no form. |
| `POST /app/inventory/{vin}/delete` | `VehicleDeletePost` | 303 to the list; 422 for a sold vehicle; 400 for the envelope. |
| `POST /app/inventory/{vin}/photos` | `VehiclePhotoPost` | 303 to the vehicle with a notice; the vehicle's page as 422 with the reason; 403 for a salesperson. |
| `GET /app/inventory/{vin}/photos/{position}` | `VehiclePhotoStream` | The photo at that position, from 1, inline; 404 for none. |
| `POST /app/inventory/{vin}/photos/{position}/delete` | `VehiclePhotoDeletePost` | 303 to the vehicle with a notice; 400 for the envelope; 403 for a salesperson; 404 for no photo there. |

Every vehicle page is one wide `vehicle` panel in the staff pages' document, framed by `staff`'s `StaffPanels`, which `VehicleServices` builds with that id stem; the 404, 400 and sold pages are its warning page.

The form (`VehicleForm`, its grid from `VehicleFields`) is AIC's `Form` atom around the theme's `FormGrid` of `FormField`s and the status `SelectField`, then `FormActions`. No field is browser-required, so every post reaches `VehicleChecks`:

- the VIN: 17 letters and digits without I, O or Q (taken in capitals), its ISO 3779 check digit right (`Vins::checkDigit()`; the message names the digit it should be), and no other vehicle holding it;
- the year: a model year from 1981 to next year, by the database's date;
- make, model, body style and colour required, trim optional, each within its column's length;
- mileage, list price and cost: whole numbers from 0, one to seven digits;
- the status: one of the three, and sold while a sale is recorded (`VehicleSaves`; void the sale first); the day it came onto the lot: a real `YYYY-MM-DD`.

A refusal answers 422 with the error alert above the fields, each message beside its field, and every value as typed. The create's insert skips a VIN taken since the check (`EntityStore::insertIfAbsent()`), and an update that meets one on the unique key finds it taken; both answer that refusal too; the delete's condition keeps a sold row whatever the page said. A save or a delete leaves one line in the session (`StaffNotices`, the `staff` module's, which customers share) for the page it redirects to, which shows it as a success `Notice` and clears it.

The edit and delete forms carry AIC's signed envelope sealing the vehicle's path (`StaffSeals`, over `EnvelopeCodec`, from `staff` too). A post whose envelope is missing, altered or sealed for another vehicle's path answers 400 and changes nothing, so a form issued for one vehicle cannot change or delete another. The CSRF token and honeypot are the `Form` atom's.

## Photos

A vehicle's photos (#16) are the `photos` module's: its table, its files in AIC's public files (#23) and its gallery. Inventory requires it and keeps the routes, since a refused upload answers the whole vehicle page, which only inventory builds; `photos` knows a vehicle by its numeric id alone, which `VehicleRecords::vehicleId()` finds by the route's VIN. `VehiclePhotoForms`, in `Form/`, holds the three routes' flows:

- an upload from a sales manager or an admin goes to `VehiclePhotos::add()`; its refusal (no file, a file AIC's `UploadCheck` refuses by its content or its size, a thirteenth photo) answers `VehicleCards::page()` with the reason and 422, and Tessera puts it in place; a kept photo answers 303 to the vehicle with "Added a photo of the {title}.";
- a delete names the photo by its position, and its form seals that photo's id (`VehiclePhoto::sealed()`), so a form from a page that is out of date answers 400 instead of deleting the photo now at that position;
- the Stream answers `VehiclePhotos::download()`: AIC's `FileDownload` of the photo's file in the public files, with the request, which sends the ETag, `Last-Modified` and `Cache-Control: private, no-cache` and answers ranges (206, 416) and conditions (304, 412). The gallery itself shows each photo from its `/files/` URL.

`VehicleServices` builds `VehiclePhotoForms` with the photos' service from the public files, so the photo routes' generated part is one short method.

A salesperson's upload or delete answers 403 (AIC #197) and changes nothing; their page shows the photos with no form. The vehicle's page shows the gallery under its card, keyed `photos`. Deleting a vehicle deletes its photo rows by their foreign key, and `VehicleDeletes` then hands `VehiclePhotos::forget()` the photos it read before the delete, which removes each file no other photo holds.

## Sales

The `sales` module requires inventory, so inventory cannot require it back. It exports a plugin type instead, `VehicleSales` (with its value `VehicleSale`), and `sales` registers its implementation. `VehicleServices` takes every plugin with `#[Tagged(VehicleSales::class)]` and hands them to `SoldVehicles`, which asks them for a vehicle's sale and for the path of the form that records one; the generated provider builds a plugin with no arguments, so the entity store comes with each call. With a sale, the vehicle's page shows a "Sale" table (`vehicle-sale`: the day linked to the sale, the customer linked to theirs, the salesperson and the price), and the edit form refuses any status but sold. Without one, a vehicle that is not sold gets "Record a sale" (`vehicle-sell`), which opens `/app/sales/new?vehicle={VIN}`. Without the `sales` module, the page shows neither.

`VehicleStatusRepository`, exported, is how `sales` changes a vehicle: it locks a vehicle's row while a sale is recorded and sets its status (sold, or in stock when a sale is voided), on the caller's transaction. `holds` (#20) goes through it too: `lockedStatus()` locks a vehicle's row and gives its status before a hold is placed or released, and `mark()` sets it on hold or back in stock. A delete that meets a sale's foreign key, because a sale landed after an edit's check, answers the delete page as 422 (`VehicleDeletes`). Inventory also exports what the sales seed builds its sold vehicles with: `Vehicle`, `VehiclePrice`, `VehicleStatus`, `Vins`, `VehicleRepository` and `SeedVehicles`; and `VehicleTable` and `TodayRepository`.

## Seed

`bin/aic inventory:seed` takes no arguments (exit 2 with its usage otherwise) and saves 60 invented vehicles, five each of twelve makes (`SeedVehicles`). The same 60 come out every run, so a rerun updates the same rows by VIN. Each VIN (`Vins`) starts with `0LT`, a world manufacturer identifier no region is assigned, and carries the model-year code and a valid ISO 3779 check digit, so it is well formed and can belong to no real vehicle.

Run `bin/aic migrate` first. From the host, point both at DDEV's published Postgres port (`PGPORT`, from `ddev describe`), or run them with `ddev exec`.

## Checks

`config/native-site.json` asks for the list signed out (303), signs in again, then asks for it plain, filtered, sorted, at page 2 and with malformed parameters. Then it walks a vehicle's record with the fictional VIN `0LTGATE19SR999001`: the new form, a create refused on every field, on its check digit and on a taken VIN (422 each) and with no token (403), the create (303), its page and edit form, an update with an altered envelope (400), a refused update (422), an update to sold, the sold vehicle's delete page and its refused delete (422), back in stock, the delete page and the delete (303), the list with its notice, and 404 for the deleted vehicle's page and edit form and for a malformed VIN. At the end of the script, three Tessera visits ask for the results alone, filtered and sorted and at page 2, and for a component the panel lacks, which answers the whole document; the file's last steps add one by the anchor, `Tessera-Targets: @inventory-results`, which each site resolves from its own page (#13), and a search by the anchor as the box sends it (#27). The steps delete the vehicle they make, so the compiled site's pass finds the database as the PHP site's did; a run stopped half way leaves it, and the next create then answers 422 until it is deleted.

The Playwright tests `tests/browser/inventory-*.spec.ts` compare what the list shows with what the same query gives from Postgres: `tests/browser/support/inventory.ts` runs `psql` with the `PGHOST` and `PGPORT` that gate:23 and `vendor/bin/aic-gate browser` give the tests, so the seed must have run against that database. `tests/browser/inventory-table-swap.spec.ts`, `inventory-table-history.spec.ts` and `inventory-table-plain.spec.ts` hold the list to Tessera's promise: a filter, a sort and a page each make one visit of the results answered by their fragment alone, the window and the sidebar's element kept, the URL in step, back and forward restoring each, and with JavaScript off the same controls as plain GETs; `tests/browser/support/tessera.ts` records the page's requests to the list through a route, and `anchorPath()` finds the results' path from their anchor on the page, so the tests spell no path either. `tests/browser/tessera-anchors.spec.ts` holds the anchor and every control that names it. `tests/browser/search-as-you-type-inventory.spec.ts`, `search-as-you-type-stale.spec.ts`, `search-no-script.spec.ts` and `profiler-search-as-you-type.spec.ts` hold the search as you type (#27): a visit per pause, focus, caret, loading, history and reload; a held-back answer never painting over a newer search; the plain form with JavaScript off; and the profiler's toolbar listing each pause's exchange. `tests/browser/vehicle-*.spec.ts` add, refuse, edit, delete and fail to delete a sold vehicle in the browser, each on its own `0LTPW…` VINs, which `tests/browser/support/vehicles.ts` deletes before and after each test and reads back through `psql`.

## The payment calculator

A vehicle's page shows finance's payment calculator (#26) after its card, at its list price and the dealership's default APR. `VehicleServices` takes the `FinanceRates` plugins (`#[Tagged]`) and builds finance's `DealershipRates` over them, which asks settings' plugin. That plugin is the one that names the dealership in the footer, and it keeps the profile it read for the request, so the page reads the profile once. The calculator works in the browser alone; finance's README says how.
