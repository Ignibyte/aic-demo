# Dashboard

`/app`, the first page signed-in staff see (#8): the greeting in the header, this month's figures as stat tiles, and three charts drawn as SVG on the server. The module requires `inventory`, `sales` and `staff`. It owns the one route and no table, command or export; the page sits under `/app`, which the staff module's protected prefix keeps to signed-in staff, and it is a `StaffPages` document.

## The page

`GET /app` (`DashboardPage`) reads the member from `StaffSession` (303 to `/sign-in` when the session holds none) and hands `StaffPages` the greeting, "Welcome, {name}", as the top bar's title and "{role} at Ridgeway Motors" as the line under it. Under the header, the `PanelGrid` holds, keyed `tiles`, `sales`, `makes` and `salespeople`:

- the theme's `StatTiles` `dashboard-tiles` ("This month on the lot"), built by `DashboardTiles`: `tile-in-stock`, the vehicles in stock, with how many more are on hold; `tile-sales` and `tile-revenue`, this month's sales and the sum of their prices, each with last month's under it; and `tile-days`, the average days the vehicles not sold have been on the lot, rounded, with how many vehicles that covers;
- the wide panel `sales`, "Sales by month": the theme's `ColumnChart` `sales-chart`, a column for each of the last twelve months, the oldest first, labelled by its short name with the year under the first month and under each January;
- the panel `makes`, "Inventory by make": the theme's `BarChart` `makes-chart`, a bar for each make of the vehicles in stock or on hold, the most first, then by make;
- the panel `salespeople`, "Revenue by salesperson": the `BarChart` `salespeople-chart`, a bar for each member of staff with a sale in the twelve months, their prices summed in whole dollars, the most first, then by name.

`DashboardCharts` builds the panels and hands the theme's `ColumnCharts` and `BarCharts` the labels and whole numbers; the theme lays the charts out and its templates draw them, colours from the stylesheet's classes. Each SVG is labelled by its title element and its description element, which holds the summary `ChartSummaries` writes from the same rows (such as "44 sales over the last twelve months; the most, 6, in August 2026."), which the caption repeats for sighted readers. There is no chart script, no inline style and no `RawHtml`. `DashboardWords` writes counts with their noun and whole dollars.

## Figures

`DashboardRepository` reads the `vehicles`, `sales` and `users` tables with its own SQL, which the modules it requires own, and writes nothing. Every query counts against the database's `current_date`, so no date is worked out in PHP and both of gate:23's sites read the same day: "this month" is the database's calendar month (`to_char(…, 'YYYY-MM')`), and "the last twelve months" start on the first day of the month eleven before it, as old Lotline's ticket 9 counted them. The months come from `generate_series`, left-joined to their sales, so a month without one counts 0.

| Method | What it reads |
|---|---|
| `tiles()` | the vehicles `in_stock` and `on_hold`, the vehicles not `sold` and `round(avg(current_date - added_on))` over them, and the count and price sum of this month's and last month's sales, in one row (`TileFigures`) |
| `months()` | each of the twelve months, the oldest first: its short name, its note (the year, on the first month and on January), its full name and its count of sales (`MonthSales`) |
| `makes()` | the vehicles not sold per make (`Figure`) |
| `salespeople()` | each salesperson's price sum since the twelve months began (`Figure`) |

A row off `fetchAll()` is read through a method with a native `Row` parameter, as `SaleRows` does, since elephc reads no phpdoc.

## Checks

`config/native-site.json` ends with `GET /app` signed in (200), after the sales steps, and the staff steps still ask for it signed out (303) and signed in. The browser looks `tests/browser/dashboard-tiles.spec.ts` and `tests/browser/dashboard-charts.spec.ts` sign in through `/app`, put a vehicle in stock and one sold today (`0LTPWDSH…` VINs, to `pw.dashboard@example.test`) in the database through `psql`, and compare the greeting, every tile with its line, and each chart's title, summary and bars or columns with what their own SQL gives; the tiles look also deletes its sale and sees this month's figures drop by it. `tests/browser/support/dashboard.ts` holds the rows, their cleanup and those queries.
