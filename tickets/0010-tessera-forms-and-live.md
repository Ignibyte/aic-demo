---
number: 10
title: "Tessera: forms in place, and the dashboard live"
type: feature
status: in-review
created: 2026-10-27T11:00:00Z
updated: 2026-10-27T10:45:29Z
phase: "docs"
---

Old Lotline's ticket 11 (the private old Lotline repository) is the reference for behaviour.

- The vehicle, customer and sale forms submit through Tessera: errors render in place (422), a
  success follows the server's redirect, nothing is ever resubmitted (each write carries Tessera's
  write id), and with JavaScript off each form still works.
- The dashboard shows new sales live over Server-Sent Events: a sales feed and the month's tiles
  update when a sale is recorded, and the stream resumes from `Last-Event-ID` after a drop.
- Steps in `config/native-site.json` for the stream and a resumed stream.
- Anything AIC lacks for this is AIC work: ask the operator, never work around it in the app.

Done looks like:

- `composer run gate` passes with no deferral.
- Browser looks, kept in `tests/browser/`: a refused form is answered in place with its error
  focused and the URL kept; a saved form lands on its page without a new document; a double click
  records one write; a sale recorded in one page appears on the dashboard open in another without a
  reload; with JavaScript off the forms work. No console error under the page's
  Content-Security-Policy. They pass against DDEV and in gate:23 on the compiled site.

When this run completes, stop.

## Comments
