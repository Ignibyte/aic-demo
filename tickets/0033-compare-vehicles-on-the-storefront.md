---
number: 33
title: "Compare vehicles on the storefront"
type: feature
status: closed
created: 2026-11-02T18:30:00Z
updated: 2026-11-04T14:13:13Z
phase: "docs"
---

A visitor ticks "Compare" on up to three cars anywhere on the storefront; a compare bar at the
foot of the page follows them across pages, and "Compare" opens a side-by-side drawer (year, make,
model, trim, mileage, price, the payment from #26, photos) without changing the URL. A fourth tick
is refused with a message.

Tessera API: `data-signals__document` holding the chosen VINs across enhanced navigation,
`data-show` and `data-text` for the bar, `data-push="off"` on the drawer's request so the URL stays,
`data-target` naming the drawer region, `data-track` on the storefront's stylesheet so a deploy
mid-visit reloads cleanly, and `data-on__outside` closing the drawer.

AIC: the drawer a Page answering one region for any list of up to three VINs (`whereIn`), a 422
for a fourth. With JavaScript off "Compare" is a link carrying the VINs as GET parameters to a
plain compare page.

Every new behaviour gets a kept browser test in `tests/browser/` (also run with JavaScript off where the feature has a no-script path), and every new GET route a step in `config/native-site.json`, so gate:23 holds it on the compiled site. A defect in AIC or Tessera goes to the operator as an AIC or Tessera ticket, not a workaround here.

## Comments

### 2026-11-04T12:30:00Z

The operator checked the API before the run (AIC b874d98, Tessera 0.4.5, as #32 left them):

- Every directive the description names is in Tessera 0.4.5: `data-signals__document`,
  `data-push="off"`, `data-track` and the `outside` flag.
- `data-on__outside` is written `data-on:click__outside`: the event name follows a colon, as
  with `data-on:keydown` in #32.
- AIC has `EntityQuery::whereIn()`.
