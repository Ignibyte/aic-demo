# Reports

Two reports nobody asks for (#20), each a job that its schedule queues on a UTC cadence; the job does the work. The module requires `staff` (the users, by role), and `inventory`, `sales` and `leads`, whose tables its views read, as the dashboard's views read the tables of the modules it requires. It has no pages.

| Schedule | Cadence (UTC) | Job | What it does |
|---|---|---|---|
| `aged-stock` | `0 7 * * *` | `aged-stock` (`NotifyAgedStock`) | Lists the vehicles not sold on the lot longer than the threshold, longest first, each with its title, VIN, days and list price, in one notification to each sales manager, kept in the database and mailed, linking `/app/inventory?sort=added`. With none aged, it sends nothing. |
| `daily-digest` | `0 21 * * *` | `daily-digest` (`SendDailyDigest`) | Mails each manager (admin or sales manager) the day's sales and their revenue, its leads (questions and test drives) and its new stock. Mail alone: nothing goes to an inbox. |

The cadences are UTC, as AIC's are; the database's time zone here is UTC too, so the digest's day is the day it is sent on.

## Data

`CreateReportViews` makes two views, the one place the module writes SQL, both counting against the database's `current_date`: `report_aged_stock`, each vehicle not sold with `days`, today minus `added_on`; and `report_day`, one row of today's sales (count and revenue), leads (count, inquiries, test drives) and vehicles added. `ReportTables` holds their read-only entity types; `ReportRepository` reads them, and the members of some roles through staff's exported `StaffTable`, by id.

The threshold is typed config: `config/reports.php` returns a `ReportConfig` (`agedStockDays: 60`), which the module's factory `ReportSettings` hands to the aged-stock job. A vehicle is aged when its days on the lot are more than it, so one on the lot exactly 60 days is not.

## Delivery

Every report goes through AIC's `Notifier` (`ReportNotices`), one `notify` job per channel, so each delivery retries and fails on its own. A member whose login is not an address (the gate's own user, `gate`) gets no mail: the aged-stock list is kept for them alone, and the digest skips them. When the mail server cannot be reached, AIC #208's `notify` job answers a retry after 30 seconds at least, and fails on its fifth attempt with the transport's reason. `bin/aic queue:failed` lists the failed jobs, newest first, and `bin/aic queue:retry <id>` queues one again; both are AIC's. Two `bin/aic queue:work` processes share the queue through AIC's `FOR UPDATE SKIP LOCKED`, so no job runs twice.

## Checks

The looks run in DDEV, whose web container reaches Mailpit: `bin/aic schedule:run <YYYY-MM-DDTHH:MM>` on a minute no one ran before, `bin/aic queue:work`, and Mailpit read through its HTTP API.

- `tests/browser/aged-stock.spec.ts`: vehicles of the look's own on the lot 61, 60 and 75 days (one on hold) and one sold after 90; the 07:00 minute and one worker give each sales manager one notification whose lines are exactly the vehicles the database says are aged, mailed too, and opened from the inbox onto the inventory.
- `tests/browser/daily-digest.spec.ts`: a car added, a car sold and two leads today; the 21:00 minute mails the sales manager and the admin one digest each, whose figures match the database's own counts, and the salesperson none.
- `tests/browser/daily-digest-mail-down.spec.ts`: with `AIC_MAIL_PORT=1` the digest's delivery waits after each failed attempt, fails on the fifth with "SMTP: cannot reach", shows in `queue:failed`, and after `queue:retry` a worker delivers it once.
- `tests/browser/queue-two-workers.spec.ts`: 20 aged-stock jobs and two workers started together; their counts add up to every job and delivery, and the manager gets each notification once, stored and mailed.
