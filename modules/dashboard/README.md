# Dashboard

`/app`, the first page signed-in staff see (#8): the greeting in the header, this month's figures as stat tiles, the sales recorded last, and three charts drawn as SVG on the server. The tiles and the latest sales stay live over `/app/events`, a Tessera stream (#10). The module requires `customers`, `inventory`, `sales` and `staff`. It owns those two routes and no table, command or export; both sit under `/app`, which the staff module's protected prefix keeps to signed-in staff, and the page is a `StaffPages` document.

## The page

`GET /app` (`DashboardPage`) reads the member from `StaffSession` (303 to `/sign-in` when the session holds none) and hands `StaffPages` the greeting, "Welcome, {name}", as the top bar's title, "{role} at Ridgeway Motors" as the line under it, and `Dashboard` as the document's title (`Dashboard · Lotline`), since the greeting names the member, not the page. Under the header, the `PanelGrid` holds, keyed `tiles`, `feed`, `sales`, `makes` and `salespeople`:

- the theme's `StatTiles` `dashboard-tiles` ("This month on the lot"), built by `DashboardTiles`: `tile-in-stock`, the vehicles in stock, with how many more are on hold; `tile-sales` and `tile-revenue`, this month's sales and the sum of their prices, each with last month's under it; and `tile-days`, the average days the vehicles not sold have been on the lot, rounded, with how many vehicles that covers;
- the wide panel `feed`, "Latest sales", built by `DashboardFeed`: the theme's `LiveParts` (keyed `live`), which names the stream, around the `DataTable` `sales-feed` (keyed `list`), a row for each of the five sales recorded last, the latest first, by id: the day, linked to the sale's page; the vehicle's title; the customer; the salesperson; and the price;
- the wide panel `sales`, "Sales by month": the theme's `ColumnChart` `sales-chart`, a column for each of the last twelve months, the oldest first, labelled by its short name with the year under the first month and under each January;
- the panel `makes`, "Inventory by make": the theme's `BarChart` `makes-chart`, a bar for each make of the vehicles in stock or on hold, the most first, then by make;
- the panel `salespeople`, "Revenue by salesperson": the `BarChart` `salespeople-chart`, a bar for each member of staff with a sale in the twelve months, their prices summed in whole dollars, the most first, then by name.

`DashboardCharts` builds the panels and hands the theme's `ColumnCharts` and `BarCharts` the labels and whole numbers; the theme lays the charts out and its templates draw them, colours from the stylesheet's classes. Each SVG is labelled by its title element and its description element, which holds the summary `ChartSummaries` writes from the same rows (such as "44 sales over the last twelve months; the most, 6, in August 2026."), which the caption repeats for sighted readers. There is no chart script, no inline style and no `RawHtml`. `DashboardWords` writes counts with their noun and whole dollars.

## Live updates

`GET /app/events` (`DashboardEvents`, a Stream) answers Server-Sent Events, which Tessera opens for the `data-stream` element `LiveParts` writes: `/app/events?seen=<fingerprint>`, the sales' fingerprint the page was drawn with. The page reads the fingerprint before the figures, so a sale recorded between the two reads makes the stream patch at once instead of going unseen.

`DashboardSource` makes the events. It looks at the sales once a second, the first look at once. When their fingerprint differs from the one the client saw, it sends two `tessera-patch-elements` events in mode `inner`: the tiles at `DashboardTiles::TARGET`, `content/c0/PanelGrid.1:tiles`, then the feed's table at `DashboardFeed::TARGET`, `content/c0/PanelGrid.1:feed/Panel.1:live/LiveParts.1:list`, with the new fingerprint as its id. A look that finds nothing new sends a keep-alive. The paths are spelled as the render pass keys them, so they change if the page moves the tiles or the panel. The `LiveParts` element sits outside both patched parts, so a patch never replaces it and Tessera keeps its one connection.

`DashboardSources` reads the request into the source, out of the controller. What the client saw is `Last-Event-ID` when it is a well-formed fingerprint, so a reconnect after a drop resumes from the last event it received; otherwise it is the `seen` parameter, and otherwise nothing, so the first look patches. A client that drops between the two patches still holds the older id, and gets both again. The stream has no end: AIC closes every finished stream with `tessera-close`, after which Tessera does not reconnect, so a live page needs the connection held, and the keep-alive each second lets the server notice a client that went away. `polls`, from 1 to 60, ends it after that many looks (any other value leaves it endless), for the native steps (gate:23's PHP site cannot hold an endless answer) and for the resume look.

## Figures

`DashboardRepository` reads the `vehicles`, `sales`, `customers` and `users` tables with its own SQL, which the modules it requires own (`customers` since #10, for the latest sales' buyers), and writes nothing. Every query counts against the database's `current_date`, so no date is worked out in PHP and both of gate:23's sites read the same day: "this month" is the database's calendar month (`to_char(…, 'YYYY-MM')`), and "the last twelve months" start on the first day of the month eleven before it, as old Lotline's ticket 9 counted them. The months come from `generate_series`, left-joined to their sales, so a month without one counts 0.

| Method | What it reads |
|---|---|
| `tiles()` | the vehicles `in_stock` and `on_hold`, the vehicles not `sold` and `round(avg(current_date - added_on))` over them, and the count and price sum of this month's and last month's sales, in one row (`TileFigures`) |
| `months()` | each of the twelve months, the oldest first: its short name, its note (the year, on the first month and on January), its full name and its count of sales (`MonthSales`) |
| `makes()` | the vehicles not sold per make (`Figure`) |
| `salespeople()` | each salesperson's price sum since the twelve months began (`Figure`) |
| `fingerprint()` | the sales' count, price sum and highest id, joined by hyphens (`44-1132424-44`): a record raises the id and a void, which deletes the row, lowers the count |
| `latest(int)` | the sales recorded last, by id, the latest first, with the vehicle's title and the customer's and salesperson's names (`LatestSale`) |

A row off `fetchAll()` is read through a method with a native `Row` parameter, as `SaleRows` does, since elephc reads no phpdoc.

## Checks

`config/native-site.json` ends with `GET /app` signed in (200), after the sales steps, and the staff steps still ask for it signed out (303) and signed in. Two stream steps come last, signed in: `GET /app/events?polls=1`, a page that saw no sales, and `GET /app/events?seen=0-0-0&polls=2` with a stale `Last-Event-ID`. Both answer the two patches, the second after a further look; the sale steps before them record a sale and void it in each pass, so both sites read the same sales.

The browser looks `tests/browser/dashboard-tiles.spec.ts` and `tests/browser/dashboard-charts.spec.ts` sign in through `/app`, put a vehicle in stock and one sold today (`0LTPWDSH…` VINs, to `pw.dashboard@example.test`) in the database through `psql`, and compare the greeting, every tile with its line, and each chart's title, summary and bars or columns with what their own SQL gives; the tiles look also deletes its sale and sees this month's figures drop by it. `tests/browser/support/dashboard.ts` holds the rows, their cleanup and those queries.

`tests/browser/dashboard-live.spec.ts` holds the stream's URL and the feed to the fingerprint and the sales Postgres gives, then records a sale through the form in a second page of the same session and sees it first in the feed and counted in the tiles, with no reload and no document loaded. `tests/browser/dashboard-resume.spec.ts` ends the first connection early, as a drop would (the server's answer for four looks, without its close), and holds the reconnect while it puts a second sale in Postgres. The reconnect must name the first sale's fingerprint as `Last-Event-ID`, and the second sale must then appear. `tests/browser/support/live.ts` holds their rows (`0LTPWLIV…` VINs, `pw.live@example.test`) and the queries.
