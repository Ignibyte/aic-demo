---
number: 4
title: "Inventory: the vehicles on the lot, listed"
type: feature
status: closed
created: 2026-10-27T00:00:00Z
updated: 2026-10-27T05:44:17Z
phase: "docs"
---

Staff see the vehicles on Ridgeway Motors' lot. This is the first half of inventory; creating,
editing and deleting a vehicle is the next ticket. Old Lotline's ticket 5 (the private old Lotline repository,
read only) is the reference for behaviour, not for code.

What to build, as a new `inventory` module that requires `staff`:

- A vehicles table by migration: VIN (unique, 17 characters), year, make, model, trim, body style,
  mileage, exterior colour, list price and cost (whole dollars), status (in stock, on hold, sold),
  the date added to the lot, and timestamps.
- `bin/aic inventory:seed`: about 60 invented vehicles across a dozen makes, deterministic, so a
  rerun updates the same rows. No real VIN: generate check-digit-valid but fictional ones.
- `GET /app/inventory`, for signed-in staff: a table of vehicles with a search (make, model, VIN),
  filters (make, status, a price range), sorting by column (a second click reverses; `aria-sort`
  on the sorted column) and pagination (15 a page). Every control is a plain GET query parameter,
  so the list works without JavaScript, and every link keeps the other parameters. An unknown or
  malformed parameter falls back to its default.
- Use the theme's components: `DataTable`, `TableRow`, `FilterBar`, `SelectField`, `Pagination`,
  `StatusBadge`. Add or extend one only when none fits.
- The navigation links to the inventory for signed-in staff.
- Steps in `config/native-site.json` for the list signed in: plain, with a filter, a sort and a
  page; and signed out (303 to sign-in).
- A fix ticket 3 left: signed in, the style guide at `/theme` shows the sample `SignedInMenu` beside
  the header's real one, and the ids `account-name`, `account-role`, `sign-out-form` and `sign-out`
  each appear twice. Give the menu's ids a prefix the caller sets, so the sample uses its own, and
  add a gate:23 step for `/theme` signed in, so the HTML lint (duplicate ids) holds it from now on.

Done looks like:

- `composer run gate` passes with no deferral.
- Browser looks, each a Playwright test the run keeps in `tests/browser/`: filtering, sorting and
  paging give the rows the same query gives from the database; with JavaScript off the filter form
  still works. They pass against DDEV (https://aic-demo.ddev.site:8843) and in gate:23 on the
  compiled site, where the gate's own user is signed in (`AIC_GATE_LOGIN`, `AIC_GATE_PASSWORD`).

Do not start the vehicle forms or any other feature. When this run completes, stop.

## Comments
