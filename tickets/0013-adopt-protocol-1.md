---
number: 13
title: "Adopt AIC's protocol 1, page titles and anchors"
type: chore
status: closed
created: 2026-10-27T18:00:00Z
updated: 2026-10-27T19:18:47Z
phase: "docs"
---

AIC moved under this branch (operator, before the run): the stable checkout is at AIC `acb7124`,
Tessera 0.2.0 is pinned in `config/app.php`, the gate:23 steps send `Tessera-Protocol: 1`, and
the workflow pack is reinstalled. What changed in AIC, and what this app must follow:

- `PageDocuments::content()` and `page()` take the page's title third, required, before the
  optional layout (AIC #187). Every page call moves to that order.
- Tessera protocol 1 (AIC #189, Tessera 0.2.0): a component is marked by `data-t-c` on its one
  root element, written by the build. gate:25 refuses a component template without exactly one
  root element outside every condition and loop: wrap siblings in a `<div>`, rows in a `<tbody>`
  (DataTable and TableResults today; follow what gate:25 names). No block markers.
- Anchors (AIC #188): a `KeyedComponent` may carry an anchor, and a link, form or poll targets
  it as `@NAME`; Tessera resolves the path. Replace every hand-written component path
  (`InventoryTable::TARGET`, the dashboard's targets, `tests/browser/support/*`) with an anchor.
  gate:23 steps may send `Tessera-Targets: @NAME`; existing steps stay as they are (a run only
  adds steps).
- The entity API grew (AIC #184, #190, #191). This run does not port repositories: ticket 14 does.

Done looks like:

- `composer run gate` passes with no deferral.
- Every kept browser test passes against DDEV (https://aic-demo.ddev.site:8843) and in gate:23 on
  the compiled site, the inventory table swap and the dashboard poll included, now through
  anchors. No module, test or step spells a component path.

When this run completes, stop.

## Comments
