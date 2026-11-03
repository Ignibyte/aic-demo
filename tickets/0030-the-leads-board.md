---
number: 30
title: "The leads board"
type: feature
status: closed
created: 2026-11-02T18:30:00Z
updated: 2026-11-03T10:27:53Z
phase: "docs"
---

`/app/leads` gains a board view beside the list: columns New, Contacted, Appointment, Won, Lost,
each a component; a card is a lead. Moving a card (a "Move to" menu on each card, plus drag where
the browser supports it) changes its stage on the server and re-renders only the two columns
involved, with their counts.

Tessera API: `@patch` with two targets (the source and destination columns by anchor),
`data-on__outside` closing the card's menu, `data-loading` on the moving card, `data-focus-key`
returning focus to the moved card in its new column, and a `tessera-patch-elements` stream event
so another salesperson watching the board sees the move without reloading (the board subscribes
with `data-stream`).

AIC: a `Stream` kind pushing board changes (`EventStream`, `TesseraEvents`, `Last-Event-ID`
resume), a Job notifying the assigned salesperson on Won, and the leads entity's stage as an enum.
With JavaScript off each card's menu is a form and the board reloads.

Every new behaviour gets a kept browser test in `tests/browser/` (also run with JavaScript off where the feature has a no-script path), and every new GET route a step in `config/native-site.json`, so gate:23 holds it on the compiled site. A defect in AIC or Tessera goes to the operator as an AIC or Tessera ticket, not a workaround here.

## Comments
