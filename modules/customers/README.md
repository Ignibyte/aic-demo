# Customers

Ridgeway Motors' customers, kept by signed-in staff (#6). The module requires `staff` and nothing else: every page sits under `/app`, which the staff module's protected prefix keeps to signed-in staff, and every page is a `StaffPages` document. The record pages' panel, their one-time notice and the envelope on their forms are `staff`'s exports (`StaffPanels`, `StaffNotices`, `StaffSeals`), shared with `inventory`. Old Lotline's ticket 7 is the reference for the behaviour.

## Data

`CreateCustomersTable` makes `customers`: an identity `id`, `name` (120), `email` (254, unique), `phone` (30), `address` (300), `notes` (text), `created_at` and `updated_at`, with an index on `name` for the list's order. An email is stored trimmed and in small letters, so the unique constraint holds whatever letters it was typed in.

`Entity/CustomerDetails` is what a form or the seed gives; `Entity/Customer` is a stored customer: its id, its details and the day it was added, with `path()`, `/app/customers/{id}`. `Service/CustomerRows` reads a row into a `Customer` and gives details as a statement's values. `CustomerRepository` serves the list and the seed (the count and a page for a search, by name then id; the seed's upsert by email); `CustomerRecordRepository` reads, checks, makes, changes and deletes one customer by id. Every value travels as a bound parameter, and a search's `%`, `_` and `\` are escaped so it matches as written.

## Seed

`bin/aic customers:seed` takes no arguments (exit 2 with its usage otherwise) and saves 40 invented customers (`SeedCustomers`): twenty first names, each twice with two different last names, `first.last@example.test`, phones `(503) 555-0100` to `(503) 555-0139` in the fictional 555-01xx range, an address in invented Ridgeway, and notes on every fourth. The same 40 come out every run, so a rerun updates the same rows by email.

Run `bin/aic migrate` first. From the host, point both at DDEV's published Postgres port (`PGHOST=127.0.0.1 PGPORT=…`, from `ddev describe`), or run them with `ddev exec`.

## Pages

| Route | Controller | What it answers |
|---|---|---|
| `GET /app/customers` | `CustomerListPage` | The list: the notice a delete left, "Add a customer", the search (`q`, over name, email and phone), the table (name linked to the customer, email, phone, the day added), and the pages (`page`), 15 customers each. A `q` given as a list reads as no search, a `page` that is not 1 to 9999 as 1, and a page past the last shows the first. |
| `GET /app/customers/new` | `CustomerNewPage` | The blank form. |
| `POST /app/customers` | `CustomerCreatePost` | 303 to the new customer's page, or the form again as 422. |
| `GET /app/customers/{id}` | `CustomerPage` | The customer's `DetailCard`: name, a badge counting the purchases ("No purchases yet", "1 purchase", "3 purchases"), email, phone, address, notes and the day added; Edit, Delete and Back to the list. Under it the "Vehicles bought" table, newest first (the VIN linked to the vehicle, the vehicle, the day linked to the sale and the price), or "No vehicles bought yet." |
| `GET /app/customers/{id}/edit` | `CustomerEditPage` | The form filled from the customer. |
| `POST /app/customers/{id}` | `CustomerUpdatePost` | 303 to the customer's page, 422, or 400 for the envelope. |
| `GET /app/customers/{id}/delete` | `CustomerDeletePage` | The `ConfirmCard`: Delete, or Keep it back to the customer. A customer who bought a vehicle gets a warning `Notice` and no form. |
| `POST /app/customers/{id}/delete` | `CustomerDeletePost` | 303 to the list, with its notice; 422 for a customer who bought; or 400 for the envelope. |

The routes type the id as `{id:int}`, so the router matches digits only. `CustomerRecords` also holds it to 1 to 9 digits from 1 before any query, since a longer number would overflow the column; an id that fails, or that no customer has, answers 404 on every page and post.

The form (`CustomerForm`, its grid from `CustomerFields`) is AIC's `Form` atom around the theme's `FormGrid` of `FormField`s (the address and notes are textareas), then `FormActions`. No field is browser-required, so every post reaches `CustomerChecks`:

- the name: required, at most 120 characters;
- the email: required, at most 254 characters, a valid address as the HTML standard defines one with a dot in its domain, and no other customer's (in any letters);
- the phone: optional; when given, 7 to 30 characters of digits, spaces and `+ ( ) - .`, at least 7 of them digits;
- the address at most 300 characters and the notes at most 2,000.

A refusal answers 422 with the error alert above the fields, each message beside its field, and every value as typed. The create's insert skips an email taken since the check (`ON CONFLICT (email) DO NOTHING RETURNING id`), and an update that meets one on the unique key finds it taken; both answer that refusal too. A save or a delete leaves its notice in the session for the page it redirects to.

The edit and delete forms carry AIC's signed envelope sealing the customer's path. A post whose envelope is missing, altered or sealed for another customer's path answers 400 and changes nothing, so a form issued for one customer cannot change or delete another. The CSRF token and honeypot are the `Form` atom's.

## Purchases

The `sales` module requires customers, so customers cannot require it back. It exports a plugin type instead, `CustomerPurchases` (with its value `Purchase`), and `sales` registers its implementation. `CustomerServices` takes every plugin with `#[Tagged(CustomerPurchases::class)]` and hands them to `BoughtVehicles`, which asks them what a customer bought; the generated provider builds a plugin with no arguments, so the database comes with each call. The customer's page lists the purchases and counts them in its badge. `CustomerDeletes` refuses a customer with purchases, since the sales' foreign key keeps the row; a delete that meets that key because a sale landed after the check gives the same 422. Without the `sales` module, a customer has bought nothing.

Customers also exports `SeedCustomers` and `CustomerDetails`, whose customers the sales seed sells to.

`CustomerServices`, the manifest's factory, builds the form's, the delete's and the page's services, so the generated handler parts each make one call.

## Checks

`config/native-site.json` asks for the list, a search on page 2 and a malformed query, then walks a customer's record: the new form, a create refused on every field (422) and with no token (403), the create (303), the same email in other letters (422), the customer's page and edit form, an update with an altered envelope (400), a refused update (422), an update (303), the delete page, a delete with an altered envelope (400), the delete (303), the list with its notice, and 404 for the deleted customer's page and edit form and for an unknown id. The created customer gets a new id on each of gate:23's passes, so the create step sets `captures: true`, the later steps use `{note}`, and gate:23 reads that path as `<captured>` in both sites' answers (AIC #178). The steps delete the customer they make; a run stopped half way leaves `gate.probe@example.test`, and the next create then answers 422 until it is deleted.

The Playwright tests `tests/browser/customer*.spec.ts` follow the sidebar link and compare the list's pages and searches with the same queries in Postgres, add a customer, refuse a duplicate email beside its field, edit one and refuse a form pointed at another, and delete one after the question. `tests/browser/support/customers.ts` deletes the looks' own `pw.…@example.test` customers before and after each test, inserts the ones a test needs, and reads rows back through `psql`, so the seed must have run against the database the tests are given.
