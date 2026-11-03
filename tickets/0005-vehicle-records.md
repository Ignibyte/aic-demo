---
number: 5
title: "Inventory: a vehicle's record, created, edited and deleted"
type: feature
status: closed
created: 2026-10-27T01:00:00Z
updated: 2026-10-27T06:32:08Z
phase: "docs"
---

Staff open a vehicle from the list, add one, change one and remove one. Old Lotline's ticket 6
(the private old Lotline repository) is the reference for behaviour, not for code.

What to build, in the `inventory` module, every route under the sign-in check:

- A detail page per vehicle (`GET /app/inventory/{id}`), linked from the list.
- Create (`GET /app/inventory/new`, `POST /app/inventory`) and edit (`GET /app/inventory/{id}/edit`,
  `POST /app/inventory/{id}`) with validation: the VIN's format, check digit and uniqueness, a year
  range, mileage and prices not negative, a known status. Each error shows beside its field (422
  in place); a save redirects (303) to the detail page.
- Delete with a confirmation step (`GET /app/inventory/{id}/delete`, `POST` to delete); a sold
  vehicle cannot be deleted. A missing vehicle answers 404.
- An edit or delete form cannot post to another vehicle: seal the vehicle's path in AIC's signed
  envelope.
- Use the theme's components: `FormGrid`, `FormField`, `SelectField`, `FormActions`, `DetailCard`,
  `ConfirmCard`, `Notice`.
- Steps in `config/native-site.json` for the detail page, the forms, a refused save, a save, the
  confirmation and the delete.

Done looks like:

- `composer run gate` passes with no deferral.
- Browser looks, kept in `tests/browser/`: adding a vehicle lands on its detail page; a bad VIN is
  refused beside its field and the form keeps what was typed; editing changes the detail page;
  deleting asks first and then the vehicle is gone; a sold vehicle cannot be deleted. They pass
  against DDEV (https://aic-demo.ddev.site:8843) and in gate:23 on the compiled site.

Do not start customers or any other feature. When this run completes, stop.

## Comments
