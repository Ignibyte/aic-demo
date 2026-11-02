---
number: 6
title: "Customers: their records, created, edited and deleted"
type: feature
status: in-review
created: 2026-10-27T02:00:00Z
updated: 2026-10-27T07:43:17Z
phase: "docs"
---

Staff keep Ridgeway Motors' customers. Old Lotline's ticket 7 (the private old Lotline repository) is
the reference for behaviour, not for code.

What to build, as a new `customers` module that requires `staff`, every route under the sign-in
check:

- A customers table by migration: name, email (unique), phone, address, notes, timestamps.
- `bin/aic customers:seed`: about 40 invented customers, deterministic, `@example.test` emails.
- `GET /app/customers`: a searchable (name, email, phone), paginated list, plain GET parameters.
- A detail page, create and edit forms with validation (a name, a valid email that is unique, a
  plausible phone), errors beside their fields (422 in place), a save redirecting (303) to the
  detail page, and delete with a confirmation step. An edit or delete form cannot post to another
  customer (AIC's signed envelope). A missing customer answers 404.
- The detail page lists the vehicles the customer bought; it is empty until sales exist (ticket 7).
- The navigation links to customers for signed-in staff. Use the theme's components.
- Steps in `config/native-site.json` for the list, the detail page, the forms, a refused and a
  good save, the confirmation and the delete. A customer's page is addressed by its numeric id:
  the create step sets `captures: true`, later steps use `{note}`, and gate:23 reads the created
  path as `<captured>` on each site (AIC #178), so both sites' ids compare equal.

Done looks like:

- `composer run gate` passes with no deferral.
- Browser looks, kept in `tests/browser/`: search and paging give the rows the database gives;
  adding a customer lands on its page; a duplicate email is refused beside its field; deleting asks
  first. They pass against DDEV and in gate:23 on the compiled site.

Do not start sales or any other feature. When this run completes, stop.

## Comments
