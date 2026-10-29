# Sales

The vehicles Ridgeway Motors sells, recorded by signed-in staff (#7). The module requires `inventory`, `customers`, `staff` and `holds` (#20): every page sits under `/app`, which the staff module's protected prefix keeps to signed-in staff, and every page is a `StaffPages` document. The record pages' panel, their one-time notice and the envelope on the void form are `staff`'s (`StaffPanels`, `StaffNotices`, `StaffSeals`). Old Lotline's ticket 8 is the reference for the behaviour.

## Data

`CreateSalesTable` makes `sales`: an identity `id`; `vehicle_id`, `customer_id` and `salesperson_id`, each a bigint foreign key (to `vehicles`, `customers` and `users`) with `ON DELETE RESTRICT`; `price` in whole dollars; `sold_on`, a date; and `created_at`. `vehicle_id` is unique, so a vehicle is sold once, and `sold_on` and `customer_id` are indexed for the list and the customer's page. The foreign keys keep a sold vehicle, a customer who bought and a salesperson with sales on the record.

Every repository reads and writes through AIC's `EntityStore` and `EntityQuery`, never the raw database (#14, gate:9). `SaleTable::type()` is the `sales` entity type, the one place the table is named (AIC #185); its `vehicle_id`, `customer_id` and `salesperson_id` are `Reference` fields to the entity types inventory, customers and staff export (`VehicleTable`, `CustomerTable`, `StaffTable`), so the module names none of their tables and requires the modules that own them. It changes a vehicle only through inventory's exports: `VehicleStatusRepository` locks and sets a vehicle's status, and the seed saves its vehicles through `VehicleRepository`. `SaleRows::joined()` joins a sale to its vehicle, customer and salesperson with `with()`, as `v`, `c` and `u`, and `SaleRows::sale()` reads a joined row into a `Sale` (its id, the vehicle's VIN and title, the customer's id and name, the salesperson's name, the price and the day). `SaleRepository` serves the list, the seed and the dashboard: the months that have sales, the totals (the count, the prices' sum and that less the vehicles' cost) and a page of the sales a filter keeps, newest first, the sales staff's logins, the seed's upsert on the vehicle, and the sales recorded last with their version, which is why it is exported with `Sale`. `SaleRecordRepository` handles one sale at a time: by id, by VIN, a customer's, and the two writes. `SaleChoiceRepository` gives the form's and the filters' options, labelled in PHP, finds what a post names, and reads the database's today through inventory's `TodayRepository`. Since #22 the form's salespeople (`sellers()`) are the active ones: a member an admin deactivated is no choice there, and a post naming them is refused as a salesperson who is not on the staff. The list's salesperson filter (`staff()`) keeps every member, so their past sales still filter. Every value is bound.

## Holds

A vehicle on hold (#20, the `holds` module) sells only to the hold's customer. `SaleChecks` asks holds' exported `HoldRepository::heldForAnother()` and refuses any other customer beside the vehicle with "This vehicle is on hold for another customer." (422). `SaleWriteRepository::record()` asks again after it has locked the vehicle's row, the row a hold's placement locks too, so a hold and a sale cannot pass together; it gives up when the vehicle is held for someone else, and after it marks the vehicle sold it ends the hold (`endForSale()`, status `sold`). When the write gives up, `SaleSaves` checks the entry again, so the form says whether the vehicle was sold or held meanwhile. `SaleServices` and `SaleRecordRepository` build the `HoldRepository` over the entity store they hold, so the plugins that build `SaleRecordRepository` with no arguments of their own need none more.

## Pages

| Route | Controller | What it answers |
|---|---|---|
| `GET /app/sales` | `SaleListPage` | The list: the notice a save left, "Record a sale", the filters (`month`, a month that has sales, as `YYYY-MM`; `salesperson`, a member of staff's login), the totals of what they keep (how many, the revenue and the gross profit, price less the vehicle's cost) in the theme's `StatTiles`, the table (the day linked to the sale, the vehicle and the customer linked to theirs, the salesperson and the price), and the pages (`page`), 15 sales each, newest first. A value that is malformed, a list, or not one the filter offers reads as no filter; a `page` that is not 1 to 9999 reads as 1, and a page past the last shows the first. |
| `GET /app/sales/new` | `SaleNewPage` | The form. `?vehicle={VIN}`, from a vehicle's "Record a sale", chooses that vehicle and fills in its list price when it is on the lot and not sold. The day starts at the database's today. |
| `POST /app/sales` | `SaleCreatePost` | Records the sale and goes to it (303, "Recorded the sale."), or the form again as 422. |
| `GET /app/sales/{id}` | `SalePage` | The sale's `DetailCard`: the vehicle, its VIN, the customer, the salesperson, the price and the day; buttons to the vehicle, to the customer, to void the sale and back to the list. |
| `GET /app/sales/{id}/void` | `SaleVoidPage` | The `ConfirmCard`: Void the sale, or Keep it back to the sale. |
| `POST /app/sales/{id}/void` | `SaleVoidPost` | Removes the sale, puts the vehicle back in stock and goes to the vehicle's page with a notice (303), or 400 for the envelope. |

The routes type the id as `{id:int}`; `SaleRecords` also holds it to 1 to 9 digits from 1 before any query. An id that fails, or that no sale has, answers 404 on the sale's page, its void page and its void post.

The form (`SaleForm`, its grid from `SaleFields`) is AIC's `Form` atom around the theme's `FormGrid`: three `SelectField`s and two `FormField`s, then `FormActions`. It names the vehicle by VIN, the customer by email and the salesperson by login: each is unique in its table and the same in every database, so gate:23's steps can post a literal sale. The vehicle offers every vehicle that is not sold, and the one the post chose. No field is browser-required, so every post reaches `SaleChecks`:

- the vehicle: on the lot and not sold ("This vehicle is already sold.");
- the customer and the salesperson: one on the list (any member of staff may sell);
- the price: whole dollars from 1 to 9,999,999;
- the day: a real `YYYY-MM-DD`, not after today and not before the vehicle came onto the lot.

A refusal answers 422 with the alert above the fields, each message beside its field and every value as chosen or typed.

`SaleRecordRepository::record()` works in one `EntityStore::transaction()`: it locks the vehicle's row through `VehicleStatusRepository::lockSellable()` (`forUpdate()`), gives up when the vehicle is gone or sold, inserts the sale unless its vehicle has one (`insertIfAbsent()` on `vehicle_id`) and marks the vehicle sold; the new id leaves the closure through a `SaleWrite`. Two posts that pass the checks together therefore record one sale, and the other answers the same 422 as a sold vehicle. `void()` locks the sale, deletes it and sets its vehicle `in_stock` in one transaction. Voiding exists so a mistaken sale can be taken back, and so gate:23's steps leave the database as they found it. There is no sale edit.

The void form carries AIC's signed envelope sealing the sale's path. A post whose envelope is missing, altered or sealed for another sale answers 400 and changes nothing. The CSRF token and honeypot are the `Form` atom's.

`SaleServices` and `SaleRecordServices`, the manifest's factories, build the form's, and the sale page's and the void's services, so the generated handler parts each make one call; `SaleList` is wired directly.

## The vehicle's and the customer's pages

`inventory` and `customers` cannot require `sales`, which requires them, so each exports a plugin type and `sales` registers an implementation with `->plugin(...)`:

- `VehicleSaleLookup` implements inventory's `VehicleSales`: a vehicle's sale, shown on its page in a "Sale" table, and the path of the form that starts from it, behind the page's "Record a sale".
- `CustomerPurchaseLookup` implements customers' `CustomerPurchases`: what a customer bought, listed on their page and checked before their delete.

The generated provider builds each with no arguments, so each method takes the entity store from the module that calls it. Without `sales`, both pages work as they did before it.

## Seed

`bin/aic sales:seed` takes no arguments (exit 2 with its usage otherwise). It saves 36 invented sold vehicles (`SeedSales`), three a month from November 2025 to October 2026, models that are not on the inventory seed's lot, with VINs from inventory's `Vins` at places 60 to 95. Then it saves 44 invented sales: one for each of those 36, a few days after it came onto the lot, and one for each of the 8 vehicles `inventory:seed` marks sold, 10 days after it came. They go in turn to the customers of `SeedCustomers` and to the users whose role is salesperson or sales manager, at the list price less up to $1,000. Every vehicle it sells, the 8 included, is saved sold through inventory's `VehicleRepository` first. Every date is fixed, so the same sales come out every run, and a rerun updates them by vehicle; a sale's day is counted in PHP from its vehicle's day on the lot, over the months' lengths.

It needs `staff:seed` (with `LOTLINE_STAFF_PASSWORD`, for the sales staff), `inventory:seed` and `customers:seed` first, and answers exit 1 naming them when a salesperson, vehicle or customer is missing. Run `bin/aic migrate` before it, with `ddev exec` or against DDEV's published Postgres port.

## Checks

`config/native-site.json` asks for the list, a filtered list, its page 2 and a malformed query. Then it walks a sale with the fictional VIN `0LTGATE2XSR999002`: the vehicle made and its page, the sale form from the vehicle and blank, a sale refused on every field (422) and with no token (403), the sale to the seeded `ada.abbott@example.test` by the gate user (303, `captures: true`), the same sale again (422), the sale's page and the sold vehicle's page, its edit form and an edit back to in stock (422), the void page, a void with an altered envelope (400), the void (303), 404 for the voided sale's page and void page and an unknown id, and the vehicle's delete. The steps leave the database as they found it, so gate:23's second pass answers as its first; a run stopped half way leaves the vehicle or the sale, and the next run's create then answers 422 until they are removed.

The Playwright tests `tests/browser/sale*.spec.ts` record a sale from a vehicle's page and find it on the vehicle's and the customer's pages, refuse a second sale of one vehicle, compare the filtered list and its totals with Postgres, and void a sale. `tests/browser/support/sales.ts` deletes the looks' own sales, then their `0LTPW…` vehicles and `pw.…@example.test` customers, before and after each test, inserts what a test needs and reads rows back through `psql`; the list's look needs the seeds in the database the tests are given.
