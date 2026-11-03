---
number: 28
title: "Inline edit in the tables"
type: feature
status: closed
created: 2026-11-02T18:30:00Z
updated: 2026-11-03T05:40:40Z
phase: "docs"
---

Staff edit a vehicle's price and status, and a customer's phone, right in the list: a click on
the cell asks the server for that row as a small form; Enter or Save writes it; Escape or Cancel
asks for the row back. A refused value (a price below cost, a bad phone) answers 422 with the
error in the row, focus on the field.

Tessera API: `@get` swapping one row (a component per row, keyed, `data-t-props` carrying the
row's signed props), `@put` saving with `form`, `data-on__keydown` (Enter, Escape),
`data-ignore-morph` on the input while it is being typed into, `data-init` to focus it, and the
write id (a double Enter saves once).

AIC: `KeyedComponent` and `TesseraProps` for the row, `PageFragments` for the row answer, the form
guard and CSRF on `@put`, the same validation as the record's full form (one validator). With
JavaScript off the cell links to the record's edit page.

Every new behaviour gets a kept browser test in `tests/browser/` (also run with JavaScript off where the feature has a no-script path), and every new GET route a step in `config/native-site.json`, so gate:23 holds it on the compiled site. A defect in AIC or Tessera goes to the operator as an AIC or Tessera ticket, not a workaround here.

## Comments
