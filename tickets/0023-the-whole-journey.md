---
number: 23
title: "The whole journey, on the compiled site"
type: feature
status: open
created: 2026-10-28T12:00:00Z
updated: 2026-10-28T12:00:00Z
---

Runs 16 to 22 each tested their own part. This run tests them together, as a customer and the
staff would use the site in one day, and fixes what breaks.

- One kept browser test, `journey`, walks it end to end: a manager adds a car with photos; a
  visitor finds it on `/cars` by filter and asks about it; the worker runs; the assigned
  salesperson's bell lights live, they open the lead, put the car on hold for the customer, then
  sell it; the car leaves the public list; the dashboard's tiles and the sales report show the sale;
  the manager exports the report and the CSV holds the sale; the visitor's confirmation mail is in
  Mailpit.
- A second, `journey-no-script`, walks the visitor's half with JavaScript off.
- Fix in the app every defect the journey finds. A defect in AIC or elephc goes to the operator.
- Move the files onto AIC's storage (AIC #213) first: vehicle photos into `PublicFiles` (the
  storefront shows them from `/files/`), report exports into `PrivateFiles` (sent by the export
  Stream after its owner check). `bin/aic` passes `ConsoleKernel` the app's root. Photos and
  exports already on disk under `var/` move across, and the hand-written folder code goes.
- Run 22's loose ends: an admin who sends a set-password link to themselves gets the mail and the
  notification like anyone else (in gate:23, a step with `"capture": "/app/inbox/"` normalises the
  notification's id across the two passes, AIC #200, and `inbox:forget` resets it per pass); setting
  a password through a link ends the member's other sessions; the staff pages name the dealership
  from its profile, not by hand.
- Steps in `config/native-site.json` follow the journey's requests in order, so gate:23 compares
  the whole day between PHP and the compiled site.

Done looks like:

- `composer run gate` passes with no deferral.
- Both journeys pass against DDEV and in gate:23 on the compiled site, three times in a row each.

When this run completes, stop.

## Comments

