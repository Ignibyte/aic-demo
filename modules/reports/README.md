# Reports

Two reports nobody asks for (#20), each a job that its schedule queues on a UTC cadence; the job does the work. And the managers' reports page (#21), its CSV exports and the sales report as JSON. The module requires `staff` (the users, by role), and `inventory`, `sales`, `leads` and `customers`, whose tables its views read, as the dashboard's views read the tables of the modules it requires.

| Schedule | Cadence (UTC) | Job | What it does |
|---|---|---|---|
| `aged-stock` | `0 7 * * *` | `aged-stock` (`NotifyAgedStock`) | Lists the vehicles not sold on the lot longer than the threshold, longest first, each with its title, VIN, days and list price, in one notification to each sales manager, kept in the database and mailed, linking `/app/inventory?sort=added`. With none aged, it sends nothing. |
| `daily-digest` | `0 21 * * *` | `daily-digest` (`SendDailyDigest`) | Mails each manager (admin or sales manager) the day's sales and their revenue, its leads (questions and test drives) and its new stock. Mail alone: nothing goes to an inbox. |
| `prune-exports` | `15 4 * * *` | `prune-exports` (`PruneReportExports`) | Removes every CSV export past its seven days by the database's clock, its file and then its row (#21). A younger one stays. |

The cadences are UTC, as AIC's are; the database's time zone here is UTC too, so the digest's day is the day it is sent on.

## Data

`CreateReportViews` makes two views, the one place the module writes SQL, both counting against the database's `current_date`: `report_aged_stock`, each vehicle not sold with `days`, today minus `added_on`; and `report_day`, one row of today's sales (count and revenue), leads (count, inquiries, test drives) and vehicles added. `ReportTables` holds their read-only entity types; `ReportRepository` reads them, and the members of some roles through staff's exported `StaffTable`, by id.

`CreateReportRangeViews` (#21) makes the reports page's five: `report_calendar` (every good day, with its month), `report_ranges` (the presets), and `report_sales`, `report_stock` and `report_leads`, one row per sale, vehicle and lead, each day as text `YYYY-MM-DD`. `CreateReportExportsTable` makes `report_exports` (the member, cascading with them; the report; the range; `queued` or `ready`; when it was asked for), and `CreateReportExportsView` the `report_export_states` view over it. `ExportTables` holds the exports' types.

The threshold is typed config: `config/reports.php` returns a `ReportConfig` (`agedStockDays: 60`), which the module's factory `ReportSettings` hands to the aged-stock job. A vehicle is aged when its days on the lot are more than it, so one on the lot exactly 60 days is not.

## Delivery

Every report goes through AIC's `Notifier` (`ReportNotices`), one `notify` job per channel, so each delivery retries and fails on its own. A member whose login is not an address (the gate's own user, `gate`) gets no mail: the aged-stock list is kept for them alone, and the digest skips them. When the mail server cannot be reached, AIC #208's `notify` job answers a retry after 30 seconds at least, and fails on its fifth attempt with the transport's reason. `bin/aic queue:failed` lists the failed jobs, newest first, and `bin/aic queue:retry <id>` queues one again; both are AIC's. Two `bin/aic queue:work` processes share the queue through AIC's `FOR UPDATE SKIP LOCKED`, so no job runs twice.

## The reports page

| Route | Controller | What it answers |
|---|---|---|
| `GET /app/reports` | `ReportsPage` | A manager's four reports over a range (#21); 422 for a range that is not one, with each error beside its field and this month's reports; 403 in the staff frame for a salesperson. |
| `POST /app/reports/exports` | `ReportExportPost` | Stores an export of the posted report over the posted range and queues its `report-export` job, in one transaction; 303 to `/app/reports?from=…&to=…&export={id}`. 422 for no report the page has or a range that is not one; 403 for a salesperson or without the form token. |
| `GET /app/reports/exports/{id}` | `ReportExportStream` | The member's own ready export within its seven days, as `text/csv; charset=utf-8` saved as `<report>-<from>-to-<to>.csv`, through AIC's `FileDownload` (206, 304, 412, 416). Every other case is 404. |
| `GET /api/reports/sales` | `SalesReportApi` | The sales report as JSON for a manager: `from`, `to`, `sales`, `revenue` and each month's `month`, `sales` and `revenue`. This month when neither day is given; 422 JSON for a range that is not one, 401 JSON signed out, 403 JSON for a salesperson. |

The managers are the admins and the sales managers (`ReportAccess`), as leads has it; the sidebar's Reports link shows to them alone.

### Ranges

A range is two days, `from` and `to`, each `YYYY-MM-DD`. Neither gives this month. Each must be a day `report_calendar` holds (2000-01-01 to the end of next year), and the first not after the last; both compare as text, so PHP works out no date (`RangeChecks`). The presets (this month, last month, this quarter, this year) come from `report_ranges`, which counts from the database's `current_date`. The presets are the theme's `RangeLinks` and the two days the theme's `FilterBar`; both are plain GET links and a form, which Tessera answers in the content region in place and which work as they are with JavaScript off.

### The reports

Each report is a list of `ReportFigure`s (a key, a label, a count and an amount), read by `FigureRepository` from a view with one row per record, the range as its conditions, through AIC's `EntityTotals`:

| Report | View | Lines |
|---|---|---|
| Sales by month | `report_sales` | Every month the range touches, from `report_calendar`, with the sales made in the range (`countBy`) and their revenue (`sumBy`, AIC #211); 0 for none. |
| Revenue and units by salesperson | `report_sales` | Each salesperson who sold in the range, grouped by login, with their units and revenue, the most revenue first. |
| Inventory by make | `report_stock` | Each make with vehicles on the lot at some time in the range: come by its last day (`added_on <= to`) and not gone before its first (`left_on >= from`). Its count, and its vehicles' average days on the lot (`averageBy`, AIC #209), counted to the sale, or for a vehicle marked sold without one the day it last changed, else to today. |
| Leads by kind | `report_leads` | Questions, then test drives, that came in during the range, and how many became a sale: the lead's car sold to a customer with the lead's email, in any case. |

`ReportCards` puts each in a panel: the theme's `ColumnChart` for sales by month, `BarChart` for the other three, a `DataTable` of the lines, and an "Export CSV" form.

## Exports

"Export CSV" posts the report and its range. `ExportRepository::queue()` inserts a `report_exports` row (`queued`) and dispatches `report-export` with its id, in one `EntityStore::transaction()`. The `report-export` job (`WriteReportExport`, through `ExportWork`) reads the same figures the page shows, writes them as CSV through AIC's `CsvDocument` (AIC #209: RFC 4180, a formula's first character guarded) to a `tempnam()` file in `var/exports/`, renames it over `{id}.csv`, then marks the export `ready` and tells its member through AIC's `Notifier` in one transaction: kept in the database, and mailed when their login is an address, linking `/app/reports/exports/{id}`. An export that is gone or ready already is left. The page at `?export={id}` says whether that export is being prepared or ready, with a "Download the CSV" link (the theme's `DownloadLink`, whose `download` attribute leaves it to the browser rather than to Tessera). `report_export_states` says whether an export is past its seven days, which both the download and `prune-exports` read.

`ReportServices` (web only, it needs the page documents) builds the access check, the range check, the export desk and the page's panels; `ExportServices` builds the jobs' `ExportWork`; both keep the generated handlers and jobs within PHPMD's coupling limit.

## Checks

The looks run in DDEV, whose web container reaches Mailpit: `bin/aic schedule:run <YYYY-MM-DDTHH:MM>` on a minute no one ran before, `bin/aic queue:work`, and Mailpit read through its HTTP API.

- `tests/browser/aged-stock.spec.ts`: vehicles of the look's own on the lot 61, 60 and 75 days (one on hold) and one sold after 90; the 07:00 minute and one worker give each sales manager one notification whose lines are exactly the vehicles the database says are aged, mailed too, and opened from the inbox onto the inventory.
- `tests/browser/daily-digest.spec.ts`: a car added, a car sold and two leads today; the 21:00 minute mails the sales manager and the admin one digest each, whose figures match the database's own counts, and the salesperson none.
- `tests/browser/daily-digest-mail-down.spec.ts`: with `AIC_MAIL_PORT=1` the digest's delivery waits after each failed attempt, fails on the fifth with "SMTP: cannot reach", shows in `queue:failed`, and after `queue:retry` a worker delivers it once.
- `tests/browser/queue-two-workers.spec.ts`: 20 aged-stock jobs and two workers started together; their counts add up to every job and delivery, and the manager gets each notification once, stored and mailed.

The reports page's looks make their own staff, customers, vehicles, sales and leads in 2001, a range no other data has, and work out every figure they expect from the base tables, never the views (`tests/browser/support/reports.ts`):

- `tests/browser/reports-figures.spec.ts`: each report's table over 2001-03-01 to 2001-04-30 is the database's, records on either side left out; the API answers the same, and 422 for a day that is not one.
- `tests/browser/reports-range.spec.ts`: the dates and a preset change the reports in the same document; a bad day and a reversed range answer 422 beside their fields; with JavaScript off the form and a preset load the range.
- `tests/browser/reports-export.spec.ts`: each report's export prepares, then after one worker is ready, notified (kept and mailed) and downloaded under its name with the table's rows; resumed from its tenth byte with 206; and saved from the inbox's opening too.
- `tests/browser/reports-export-other.spec.ts`: another manager gets 404 for a member's ready export, and no line about it on the page; its member still downloads it.
- `tests/browser/reports-salesperson.spec.ts`: a salesperson sees no Reports link, and the page (403 in the staff frame), an export post (403, nothing stored) and the API (403 JSON) refuse them.
- `tests/browser/reports-export-prune.spec.ts`: of two ready exports backdated eight and six days, the 04:15 minute and one worker remove the older, row and file, and keep the younger.

gate:23's steps open the page plain, by range, by a year, refused twice (422) and as a Tessera fragment of `content`; post an export without a token (403), for no report and for a refused range (422), and for September 2026 (303); open its `{location}` (AIC #212, the path and query of the post's 303) preparing; run the worker on a `/health` step (`"work": true`, AIC #209), where no bell shows the notification it leaves; open `{location}` again, ready, after `"console": "inbox:forget gate"` (AIC #210), capturing the download link as `{note}`; download it whole (200), by range (206) and unchanged (304); an unknown export (404); the API by range, for this month and refused (422); and signed out the API (401), the page and the download (303).
