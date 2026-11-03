---
number: 15
title: "Tessera protocol 2: read props by anchor; the live part back in its panel grid"
type: chore
status: closed
created: 2026-10-28T04:30:00Z
updated: 2026-10-28T04:37:15Z
phase: "docs"
---

AIC moved under this branch (operator, before the run): the stable checkout is at AIC `ec14588`,
Tessera 0.3.0 is pinned in `config/app.php`, the gate:23 steps send `Tessera-Protocol: 2`, and
`vendor/bin/aic-gate init` wrote the new `.semgrepignore` stub. What changed in AIC:

- Tessera protocol 2 (AIC #194, Tessera #34): Tessera ends each `Tessera-Targets` entry with the
  component's anchor (`content/c0/PanelGrid.1:live <props> @dashboard-live`). `TesseraProps::forTarget()`
  and `unchanged()` take `@NAME`, and a keyed component prints its props and anchor wherever it
  renders, inside another component's template too (`framework/docs/tessera.md`). A region wrapper
  keeps its own key so siblings coming and going do not move it.
- gate:15 refuses a positional Tessera path (`cN`, `Name.N` segments) in modules, themes and
  browser tests (AIC #195); the `.semgrepignore` stub lets it reach `tests/browser/`.
- elephc releases what a request held (AIC #61): nothing to change here.

This run:

- Read every component's props by its anchor: `DashboardPoll` and anything else that calls
  `TesseraProps` passes `@NAME`, and `StaffPages::target()` goes if nothing needs a path any more.
- Put the dashboard's live part back inside its panel grid if the page reads better that way (run
  14 moved it to `content/live` only because a nested part could not carry props); keep its 204
  poll working.
- Fix anything gate:15 now finds in modules, themes or browser tests: anchors, not paths.
- Steps in `config/native-site.json` are added for anything new; existing steps stay.

Done looks like:

- `composer run gate` passes with no deferral.
- Every kept browser test passes against DDEV and in gate:23 on the compiled site; the dashboard's
  idle poll still answers 204 and its refresh still leaves the charts alone.

When this run completes, stop.

## Comments
