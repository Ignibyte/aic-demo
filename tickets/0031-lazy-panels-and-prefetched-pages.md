---
number: 31
title: "Lazy panels and prefetched pages"
type: feature
status: closed
created: 2026-11-02T18:30:00Z
updated: 2026-11-04T03:27:26Z
phase: "docs"
---

The vehicle page's heavy parts load after the page: price history (a server-drawn SVG line),
similar vehicles (same make and body, nearest price), and the lead and hold history. Each shows a
skeleton until it arrives. In the inventory list, hovering or focusing a row prefetches that
vehicle's page, so the click paints at once.

Tessera API: `data-lazy` on each panel (with a skeleton the server renders), `data-prefetch` on
the row links, `data-permanent` on the vehicle page's photo gallery so navigating between two
vehicles keeps the gallery element (and its scroll) where the markup matches, and
`data-only-if-missing` where a panel already arrived.

AIC: each panel a Page answering only its component, `PageUnchanged` (204) when a lazy panel's data
has not changed since the page rendered, and the queries kept to what each panel shows (the
profiler, #25, shows it). With JavaScript off the panels render inline.

Every new behaviour gets a kept browser test in `tests/browser/` (also run with JavaScript off where the feature has a no-script path), and every new GET route a step in `config/native-site.json`, so gate:23 holds it on the compiled site. A defect in AIC or Tessera goes to the operator as an AIC or Tessera ticket, not a workaround here.

## Comments
