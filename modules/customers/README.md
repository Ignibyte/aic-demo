# Customers

Ridgeway Motors' customers, kept by signed-in staff (#6). The module requires `staff` and nothing else: every page sits under `/app`, which the staff module's protected prefix keeps to signed-in staff, and every page is a `StaffPages` document. The record pages' panel, their one-time notice and the envelope on their forms are `staff`'s exports (`StaffPanels`, `StaffNotices`, `StaffSeals`), shared with `inventory`. Old Lotline's ticket 7 is the reference for the behaviour.

## Data

`CreateCustomersTable` makes `customers`: an identity `id`, `name` (120), `email` (254, unique), `phone` (30), `address` (300), `notes` (text), `created_at` and `updated_at`, with an index on `name` for the list's order. An email is stored trimmed and in small letters, so the unique constraint holds whatever letters it was typed in.

`Entity/CustomerDetails` is what a form or the seed gives; `Entity/Customer` is a stored customer: its id, its details and the day it was added, with `path()`, `/app/customers/{id}`. `Entity/CustomerTable` is the table's AIC entity type, the one place it is named (#14, AIC #185); it is exported, so `sales` references a customer and reads its choices through it and the dashboard counts it. `Service/CustomerRows` reads a row into a `Customer` (the day it was added is the date part of `created_at`) and gives details as values by field. `CustomerRepository` serves the list and the seed (the count and a page for a search, by name then id; the seed's upsert by email); `CustomerRecordRepository` reads, checks, makes, changes and deletes one customer by id. Both go through AIC's `EntityStore` and `EntityQuery`, never the raw database (gate:9): every value is bound, and a search's `%` and `_` match as written.

## Seed

`bin/aic customers:seed` takes no arguments (exit 2 with its usage otherwise) and saves 40 invented customers (`SeedCustomers`): twenty first names, each twice with two different last names, `first.last@example.test`, phones `(503) 555-0100` to `(503) 555-0139` in the fictional 555-01xx range, an address in invented Ridgeway, and notes on every fourth. The same 40 come out every run, so a rerun updates the same rows by email.

Run `bin/aic migrate` first. From the host, point both at DDEV's published Postgres port (`PGHOST=127.0.0.1 PGPORT=…`, from `ddev describe`), or run them with `ddev exec`.

## Pages

| Route | Controller | What it answers |
|---|---|---|
| `GET /app/customers` | `CustomerListPage` | The list: the notice a delete left, "Add a customer", the search (`q`, over name, email and phone), then the results: the table (name linked to the customer, email, phone, the day added) and the pages (`page`), 15 customers each. A `q` given as a list reads as no search, a `page` that is not 1 to 9999 as 1, and a page past the last shows the first. |
| `GET /app/customers/new` | `CustomerNewPage` | The blank form. |
| `POST /app/customers` | `CustomerCreatePost` | 303 to the new customer's page, or the form again as 422. |
| `GET /app/customers/{id}` | `CustomerPage` | The customer's `DetailCard`: name, a badge counting the purchases ("No purchases yet", "1 purchase", "3 purchases"), email, phone, address, notes and the day added; Edit, Delete and Back to the list. Under it the "Vehicles bought" table, newest first (the VIN linked to the vehicle, the vehicle, the day linked to the sale and the price), or "No vehicles bought yet." |
| `GET /app/customers/{id}/edit` | `CustomerEditPage` | The form filled from the customer. |
| `POST /app/customers/{id}` | `CustomerUpdatePost` | 303 to the customer's page, 422, or 400 for the envelope. |
| `GET /app/customers/{id}/delete` | `CustomerDeletePage` | The `ConfirmCard`: Delete, or Keep it back to the customer. A customer who bought a vehicle gets a warning `Notice` and no form. |
| `POST /app/customers/{id}/delete` | `CustomerDeletePost` | 303 to the list, with its notice; 422 for a customer who bought; or 400 for the envelope. |

### The list's results and search as you type

The results (the table and, past one page, the pages) are an AIC `SourcedComponent` (#27): `CustomerResults` holds the repository and the parsed query and counts, pages and reads the customers in `build()`, which the render pass calls only when it renders them, so a Tessera answer for anything else never reads them. `CustomerList` puts them in the panel as the part `table`, anchored `customer-results` (`CustomerTable::ANCHOR`), and the search form and every page link carry `data-target="@customer-results"`, so Tessera asks for the results alone and sends the path it finds from the anchor; no code here spells it.

The search form is `CustomerFilters`' filter bar, and its box is the theme's `SearchField`, with the id `filter-q` and the name `q`. Each pause of 300 ms in typing submits the form (`data-on:input__debounce.300ms="el.form.requestSubmit()"`): a Tessera visit of the results with the query in the URL, so a reload shows the same list. There is no `@get`, since an action never touches history in Tessera. The form carries `data-push="replace"` (Tessera #37), so each pause replaces the list's history entry rather than adding one; its Filter button carries `data-push="push"`, which overrides the form's, so a pressed Filter still adds one, as a page link does. A newer search supersedes one still in flight, and a late answer is dropped (Tessera #38). The box sits outside the results, so their answer leaves its focus and caret alone, and `data-focus-key="customers-search"` names it for Back and Forward; there the list, its count and the URL follow the entry, and the box keeps the text last typed. While the request is out the form carries `data-loading` (the box's spinner) and the results `aria-busy="true"` (dimmed). With JavaScript off the form is the plain GET form with its Filter button. The search reads through `EntityQuery::search()`, an `ILIKE` per column joined by `OR`; `whereAny()` takes only `=`, `<>`, `<=` and `>=`, none of which matches part of a value.

The routes type the id as `{id:int}`, so the router matches digits only. `CustomerRecords` also holds it to 1 to 9 digits from 1 before any query, since a longer number would overflow the column; an id that fails, or that no customer has, answers 404 on every page and post.

The form (`CustomerForm`, its grid from `CustomerFields`) is AIC's `Form` atom around the theme's `FormGrid` of `FormField`s (the address and notes are textareas), then `FormActions`. No field is browser-required, so every post reaches `CustomerChecks`:

- the name: required, at most 120 characters;
- the email: required, at most 254 characters, a valid address as the HTML standard defines one with a dot in its domain, and no other customer's (in any letters);
- the phone: optional; when given, 7 to 30 characters of digits, spaces and `+ ( ) - .`, at least 7 of them digits;
- the address at most 300 characters and the notes at most 2,000.

A refusal answers 422 with the error alert above the fields, each message beside its field, and every value as typed. The create's insert skips an email taken since the check (`EntityStore::insertIfAbsent()` on `email`), and an update that meets one on the unique key finds it taken; both answer that refusal too. A save or a delete leaves its notice in the session for the page it redirects to.

The edit and delete forms carry AIC's signed envelope sealing the customer's path. A post whose envelope is missing, altered or sealed for another customer's path answers 400 and changes nothing, so a form issued for one customer cannot change or delete another. The CSRF token and honeypot are the `Form` atom's.

### Edit in the row

A customer's phone is edited in the list itself (#28), as a vehicle's price and status are in the inventory: each row is `CustomerRowForm`'s, keyed and anchored `customer-<id>`, its props the customer's path sealed by `StaffSeals::props()`; its phone cell (`Add a phone` when there is none) links to the customer's edit page and, with script, asks `@get('/app/customers/<id>/row/edit')` for the row as a small form. Enter or Save sends `@put('/app/customers/<id>/row', {form: true})`, once while a save is out; Escape or Cancel asks `@get('/app/customers/<id>/row')` for the row back. The theme's `EditRow` carries the keys, the focus and `data-ignore-morph` while the phone is typed into.

| Route | Class | Answers |
|---|---|---|
| `GET /app/customers/{id}/row` | `CustomerRowPage` | the row shown, or 404. |
| `GET /app/customers/{id}/row/edit` | `CustomerRowEditPage` | the row as a form, or 404. |
| `PUT /app/customers/{id}/row` | `CustomerRowPut` | the row shown with what was saved; the row as a form again, 422; 400 for props that are not this row's; or 404. |

`CustomerRowEdits` answers each as the customers page with the list's panel holding that row alone, under the list's keys (`CustomerList::KEY`, `CustomerList::RESULTS`, `CustomerTable::alone()`), so Tessera gets the row's fragment alone; the panel's line there is a fixed one, so a row's answer reads nothing but the customer. The save is held to the row's props, fills an entry from the stored customer with the posted phone put in (`CustomerEntries::row()`) and runs `CustomerChecks::errors()` on it, the full form's checks, before the repository's `update()`; a refused phone answers 422 in the row, the field marked and focused.

## Purchases

The `sales` module requires customers, so customers cannot require it back. It exports a plugin type instead, `CustomerPurchases` (with its value `Purchase`), and `sales` registers its implementation. `CustomerServices` takes every plugin with `#[Tagged(CustomerPurchases::class)]` and hands them to `BoughtVehicles`, which asks them what a customer bought; the generated provider builds a plugin with no arguments, so the entity store comes with each call. The customer's page lists the purchases and counts them in its badge. `CustomerDeletes` refuses a customer with purchases, since the sales' foreign key keeps the row; a delete that meets that key because a sale landed after the check gives the same 422. Without the `sales` module, a customer has bought nothing.

Customers also exports `SeedCustomers` and `CustomerDetails`, whose customers the sales seed sells to.

`CustomerServices`, the manifest's factory, builds the form's, the delete's and the page's services, so the generated handler parts each make one call.

## Checks

`config/native-site.json` asks for the list, a search on page 2 and a malformed query, a search as typed as a whole page and as Tessera fragments of the results by their anchor (#27), then walks a customer's record: the new form, a create refused on every field (422) and with no token (403), the create (303), the same email in other letters (422), the customer's page and edit form, an update with an altered envelope (400), a refused update (422), an update (303), the delete page, a delete with an altered envelope (400), the delete (303), the list with its notice, and 404 for the deleted customer's page and edit form and for an unknown id. Before the delete it asks for the customer's row shown and as a form, and for its save with no token (403) and with no row props (400) (#28); seeded customer 1's row is asked for as Tessera fragments by its anchor, `@customer-1`, since a step's header takes no `{note}`, and a visitor asking for a row is sent to sign in. The created customer gets a new id on each of gate:23's passes, so the create step sets `captures: true`, the later steps use `{note}`, and gate:23 reads that path as `<captured>` in both sites' answers (AIC #178). The steps delete the customer they make; a run stopped half way leaves `gate.probe@example.test`, and the next create then answers 422 until it is deleted.

The Playwright tests `tests/browser/customer*.spec.ts` follow the sidebar link and compare the list's pages and searches with the same queries in Postgres, add a customer, refuse a duplicate email beside its field, edit one and refuse a form pointed at another, and delete one after the question. `tests/browser/search-as-you-type-customers.spec.ts` holds the anchored results, a visit of them per pause in typing, focus and caret, the pages, history and reload, and `search-no-script.spec.ts` the plain form with JavaScript off. `tests/browser/support/customers.ts` deletes the looks' own `pw.…@example.test` customers before and after each test, inserts the ones a test needs, and reads rows back through `psql`, so the seed must have run against the database the tests are given.
