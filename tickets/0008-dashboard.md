---
number: 8
title: "The dashboard: stat tiles and server-drawn charts"
type: feature
status: closed
created: 2026-10-27T02:00:00Z
updated: 2026-10-27T09:07:22Z
phase: "docs"
---

`/app` becomes the dashboard. Old Lotline's ticket 9 (the private old Lotline repository) is the
reference for behaviour, not for code.

What to build, in a new `dashboard` module that requires `inventory`, `sales` and `staff`:

- Stat tiles: vehicles in stock, sales this month, revenue this month, average days on the lot
  (the theme's `StatTiles`).
- Charts drawn as SVG on the server with the theme's `BarChart` and `ColumnChart`: sales by month
  (the last 12 months), inventory by make, revenue by salesperson. Each has a title and a text
  summary for screen readers; colours come from the theme's stylesheet classes. No JavaScript chart
  library, no inline style (the HTML lint and the Content-Security-Policy refuse it), no RawHtml.
- The greeting `/app` showed moves into the dashboard's header.
- A step in `config/native-site.json` for `/app` signed in.

Done looks like:

- `composer run gate` passes with no deferral.
- Browser looks, kept in `tests/browser/`: each tile and each chart's bars or columns match what
  the same queries return from the database; each chart has its title and summary. They pass
  against DDEV and in gate:23 on the compiled site.

Do not start any Tessera work or other feature. When this run completes, stop.

## Comments
