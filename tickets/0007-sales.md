---
number: 7
title: "Sales: a vehicle sold to a customer"
type: feature
status: closed
created: 2026-10-27T02:00:00Z
updated: 2026-10-27T08:44:54Z
phase: "docs"
---

Staff record a sale. Old Lotline's ticket 8 (the private old Lotline repository) is the reference for
behaviour, not for code.

What to build, as a new `sales` module that requires `inventory`, `customers` and `staff`, every
route under the sign-in check:

- A sales table by migration: the vehicle, the customer, the salesperson (a member of staff), the
  sale price (whole dollars) and the sale date.
- Record a sale from a vehicle's page or from `GET /app/sales/new`: choose the customer and the
  salesperson, enter the price and the date. Recording it marks the vehicle sold in the same
  transaction; a vehicle can be sold once, and a second attempt is refused in place (422).
- `GET /app/sales`: a list with filters by month and salesperson, and the period's totals.
- A customer's page lists what they bought; a vehicle's page shows its sale.
- `bin/aic sales:seed`: invented sales over the past 12 months, deterministic, so the dashboard
  (ticket 8) has data; it marks those vehicles sold.
- The navigation links to sales. Use the theme's components. Steps in `config/native-site.json`
  for the list, the filters, the new-sale form, a refused and a good sale.

Done looks like:

- `composer run gate` passes with no deferral.
- Browser looks, kept in `tests/browser/`: recording a sale marks the vehicle sold and shows on
  the customer's page; selling it again is refused; the filtered list and its totals match the
  database. They pass against DDEV and in gate:23 on the compiled site.

Do not start the dashboard or any other feature. When this run completes, stop.

## Comments
