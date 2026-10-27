---
number: 12
title: "Dashboard: refresh by Tessera's poll instead of a stream"
type: feature
status: open
created: 2026-10-28T09:00:00Z
updated: 2026-10-28T09:00:00Z
---

Chad, 2026-10-28: the dashboard's live stream (ticket 10) holds one compiled-site worker for every open
dashboard while the server polls the database once a second anyway. Tessera's `data-poll` does the same
job with none held: each refresh is one ordinary request, it slows to one in twenty while the window has
no focus and stops while the tab is hidden.

- The dashboard's tiles and "Latest sales" feed refresh through `data-poll` every 5 seconds (Tessera's
  `refresh` request for the component holding them), answered by the dashboard page itself as a
  fragment.
- The stream goes: `GET /app/events`, its controller, its source and anything only it used. The operator
  has already taken its two steps out of `config/native-site.json` (this branch's base).
- Steps in `config/native-site.json` for a refresh of the polled part.
- Anything AIC or Tessera lacks for this is their work: ask the operator, never work around it.

Done looks like:

- `composer run gate` passes with no deferral.
- Browser looks, kept in `tests/browser/`: a sale recorded in one page appears on the dashboard open in
  another within about 5 seconds without a reload or a new document; while the dashboard's tab is
  hidden no refresh is sent; no request stays open. The stream's looks (`dashboard-live`,
  `dashboard-resume`) are rewritten or dropped to match. No console error under the
  Content-Security-Policy. They pass against DDEV and in gate:23 on the compiled site.

When this run completes, stop.

## Comments
