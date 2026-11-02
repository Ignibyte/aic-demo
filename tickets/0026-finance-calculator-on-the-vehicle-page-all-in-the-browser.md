---
number: 26
title: "Finance calculator on the vehicle page, all in the browser"
type: feature
status: open
created: 2026-11-02T18:30:00Z
updated: 2026-11-02T18:30:00Z
---

A buyer on `/cars/{vin}` and staff on `/app/inventory/{vin}` see a payment calculator beside the
price: down payment (a range and a number, kept in step), term (36, 48, 60, 72 months), APR, and
the trade-in value. The monthly payment, the amount financed and the total interest update on every
keystroke with **no request**: Tessera's local state only.

Tessera API it must exercise: `data-signals` (with defaults from the server: price, the
dealership's default APR from settings), `data-bind` on each input, `data-computed` for the
payment, `data-text` to show it, `data-show` (a "down payment covers the price" note),
`data-class` (warn above a payment-to-price threshold), `data-attr` (the range's max follows the
price), `data-effect` (none other than keeping range and number in step, if `data-bind` alone
cannot).

Formatting: the csp expression engine cannot format money by itself. **Open question for Chad,
answered before the run:** (a) allow one theme helper script (`themes/lotline/assets/helpers.js`,
`start({ helpers: { money } })`, Lotline's only JavaScript), or (b) no helper, whole dollars by
arithmetic in the expression. The ticket is done either way once the choice is recorded.

With JavaScript off the calculator is hidden and a server-rendered table of example payments
(3 terms) shows instead.

Every new behaviour gets a kept browser test in `tests/browser/` (also run with JavaScript off where the feature has a no-script path), and every new GET route a step in `config/native-site.json`, so gate:23 holds it on the compiled site. A defect in AIC or Tessera goes to the operator as an AIC or Tessera ticket, not a workaround here.

## Comments

### 2026-11-02T19:30:00Z

Chad chose (a): one theme helper script, `themes/lotline/assets/helpers.js`, registering a `money` helper through `start({ helpers })` (the IIFE started manually with `tessera-start` set to `manual`). It is Lotline's only JavaScript and holds no app logic beyond formatting.
