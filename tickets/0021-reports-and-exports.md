---
number: 21
title: "Reports and exports"
type: feature
status: open
created: 2026-10-28T12:00:00Z
updated: 2026-10-28T12:00:00Z
---

Old Lotline's Reports page was a placeholder. This run makes it real at `/app/reports`, for
managers and admins.

- For a date range (this month by default; presets for last month, this quarter and this year):
  sales by month, revenue and units by salesperson, inventory by make with average days on the lot,
  and lead counts by kind with how many became a sale. Figures come from views and AIC's totals,
  charts from the theme. Changing the range refreshes the report in place and works with
  JavaScript off.
- "Export CSV" on each report queues a job that writes the report's rows to a file and notifies the
  member who asked, with a link. The link downloads the file (a Stream route, `text/csv`, with a
  file name, ranges honoured so a download resumes). Only that member can download it (another
  member is 404); exports older than seven days are removed by a schedule.
- `GET /api/reports/sales?from=&to=` answers the sales report as JSON for a signed-in manager, and
  401 or 403 as JSON otherwise.
- A salesperson sees no Reports link and is refused at `/app/reports` (403 in the app's look).
- Steps in `config/native-site.json` reach every new route, a refused case included; existing steps
  stay.
- Anything AIC lacks for this is AIC work: ask the operator with `bin/aic workflow:ask`, never work
  around it in the app.

Done looks like:

- `composer run gate` passes with no deferral.
- Browser looks, kept in `tests/browser/`: each report's figures match the database for a chosen
  range; an export, once the worker runs, notifies its member and downloads the same rows; another
  member's export link is 404; a salesperson is refused. They pass against DDEV and in gate:23 on
  the compiled site.

When this run completes, stop.

## Comments

