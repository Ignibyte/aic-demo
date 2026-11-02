---
number: 29
title: "Bulk edit on inventory"
type: feature
status: open
created: 2026-11-02T18:30:00Z
updated: 2026-11-02T18:30:00Z
---

Staff tick vehicles in the inventory list (a box per row and one for the page) and act on them at
once: change status (in stock, on hold, sold is refused), move to another lot location, or delete
(with a confirm card). Only the rows touched re-render; the totals line follows.

Tessera API: a selection held in `data-signals__region` (ids), `data-attr` disabling the action
bar while nothing is selected, `data-text` for "3 selected", `@post` with `form` and a target
listing each touched row's anchor plus the totals (several targets in one answer), `data-on` with
`__prevent`, and the write id on the batch.

AIC: one POST controller, the write in one transaction (`Database` transaction), a refusal for any
row refusing the whole batch with a 422 naming the rows, `TesseraWriteMiddleware` making a repeat a
no-op, and an audit notification to the sales manager (Notifications, database channel). With
JavaScript off the boxes post as a plain form and the page redirects back.

Every new behaviour gets a kept browser test in `tests/browser/` (also run with JavaScript off where the feature has a no-script path), and every new GET route a step in `config/native-site.json`, so gate:23 holds it on the compiled site. A defect in AIC or Tessera goes to the operator as an AIC or Tessera ticket, not a workaround here.

## Comments
