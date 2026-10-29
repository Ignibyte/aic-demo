---
number: 17
title: "The public storefront and a vehicle feed"
type: feature
status: in-review
created: 2026-10-28T12:00:00Z
updated: 2026-10-29T02:44:56Z
phase: "docs"
---

Until now every page is for staff. This run adds the dealership's public side, for customers, at
`/cars`. Nobody signs in to it.

- `/cars` lists the vehicles for sale (never sold or held ones) with their main photo, year,
  make, model, mileage and price; it filters by make, price range and year, sorts by price and
  newest, and pages 12 at a time. Filters and pages refresh the list in place through Tessera and
  work with JavaScript off.
- `/cars/{slug}` shows one vehicle: its photos, its details and its price. The slug is readable
  (year, make, model and a short id); a sold or unknown vehicle answers 404 with the public look.
- The public pages use their own layout from the theme (a header with the dealership's name and a
  footer, no staff drawer), chosen by the controller; the staff navigation and account blocks never
  show on them, and the public header never shows under `/app`.
- `GET /api/vehicles` answers the same vehicles as JSON for listing partners: each with its
  slug, details, price as a decimal string, and photo URLs; `?make=` filters it. `GET
  /api/vehicles/{slug}` answers one, 404 as JSON for an unknown one.
- The public pages show no VIN, no cost and nothing about a customer.
- Steps in `config/native-site.json` reach every new route, a refused case included; existing steps
  stay.
- Anything AIC lacks for this is AIC work: ask the operator with `bin/aic workflow:ask`, never work
  around it in the app.

Done looks like:

- `composer run gate` passes with no deferral.
- Browser looks, kept in `tests/browser/`: a signed-out visitor filters and pages the list in
  place and opens a car; a sold car is 404; the public pages hold no staff navigation; the feed's
  vehicles match the list's. They pass against DDEV and in gate:23 on the compiled site.

When this run completes, stop.

## Comments

