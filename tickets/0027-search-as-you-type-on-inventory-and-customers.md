---
number: 27
title: "Search as you type on inventory and customers"
type: feature
status: closed
created: 2026-11-02T18:30:00Z
updated: 2026-11-03T03:52:12Z
phase: "docs"
---

The inventory and customer lists gain a search box that filters as staff type, with no button:
each pause in typing (300 ms) asks the server for the list component alone; the URL keeps the
query (`?q=`), so back and forward and a reload agree.

Tessera API: `data-on__input__debounce.300ms` calling `@get` with a `target` naming the list by its
anchor; `data-focus-key` so the box keeps focus and the caret through the morph; `data-loading`
and `aria-busy` styling while a request is out (a spinner in the box); a superseded request (typing
on) must never paint over a newer one: show it with a slow-response browser test.

AIC: the same Page answers the full document and the fragment; the query reads through
`EntityQuery` (`whereAny` over the columns), the count line says how many matched. With JavaScript
off the box is a plain GET form with a submit button.

Every new behaviour gets a kept browser test in `tests/browser/` (also run with JavaScript off where the feature has a no-script path), and every new GET route a step in `config/native-site.json`, so gate:23 holds it on the compiled site. A defect in AIC or Tessera goes to the operator as an AIC or Tessera ticket, not a workaround here.

## Comments
