---
number: 9
title: "Tessera: the inventory table refreshes in place"
type: feature
status: open
created: 2026-10-27T02:00:00Z
updated: 2026-10-27T02:00:00Z
---

The inventory list's filters, sorting and pages update only the table through Tessera, the page's
URL kept in step, instead of loading a new document. Old Lotline's ticket 10 (`/srv/stacks/lotline`,
read only) is the reference for behaviour. AIC's server half and Tessera's pinned release are
already in place; this is the first Lotline page that uses them for more than navigation.

- The inventory table is a keyed component, so a filter, a sort or a page asks Tessera for that
  component alone; the rest of the page stays as it is.
- With JavaScript off, the same filters, sorts and pages still work as plain GETs.
- Anything AIC lacks for this is AIC work: ask the operator (`bin/aic workflow:ask`), never work
  around it in the app.

Done looks like:

- `composer run gate` passes with no deferral.
- Browser looks, kept in `tests/browser/`: changing a filter, a sort and a page swaps only the
  table (the sidebar's element and a marker set on `window` survive; one request with
  `Tessera-Request`), the URL changes to match, back and forward restore it, and with JavaScript
  off the same controls reach the same rows. No console error under the page's
  Content-Security-Policy. They pass against DDEV and in gate:23 on the compiled site.

When this run completes, stop.

## Comments
