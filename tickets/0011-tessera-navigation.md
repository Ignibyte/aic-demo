---
number: 11
title: "Tessera: the whole site through enhanced navigation"
type: feature
status: in-review
created: 2026-10-27T12:00:00Z
updated: 2026-10-27T11:22:45Z
phase: "docs"
---

Every page moves through Tessera's enhanced navigation: the layout stays, the content region (and
whatever else changed) is swapped from the server's HTML, history, focus and scroll behave, and a
full document still answers every direct visit. Old Lotline's ticket 12 (the private old Lotline repository,
read only) is the reference for behaviour.

- Every link and form in the app is enhanced; a link that must load a whole document (sign-out, a
  download) says so.
- The page's title and the navigation's current item follow the page.
- Anything AIC lacks for this is AIC work: ask the operator, never work around it in the app.

Done looks like:

- `composer run gate` passes with no deferral.
- Browser looks, kept in `tests/browser/`: a walk through every page (dashboard, inventory, a
  vehicle, customers, a customer, sales, the theme guide) swaps regions instead of loading new
  documents (a marker on `window` survives), back and forward restore each page and its scroll,
  focus lands on the new content, and with JavaScript off every page still works. No console error
  under the Content-Security-Policy. They pass against DDEV and in gate:23 on the compiled site.

When this run completes, stop: this is the last Lotline run of the plan.

## Comments
