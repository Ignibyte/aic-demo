# Lotline

Lotline is the demo site for [AIC](https://github.com/Ignibyte/aic-nextgen), a PHP 8.5 framework
built for coding agents to write apps in and built to compile to a native binary with elephc. It
is a dealership's back office for the invented Ridgeway Motors: staff sign in, keep the vehicles
on the lot, the customers and the sales, and land on a live dashboard. A coding agent wrote it
through AIC's workflow, one ticket at a time (`tickets/`).

## Try it

You need git, Docker and [DDEV](https://ddev.com) 1.24.10 or later. Lotline requires AIC from a
checkout beside it, so make a folder for both:

```bash
mkdir aic && cd aic
git clone https://github.com/Ignibyte/aic-nextgen.git aic-nextgen-stable
git -C aic-nextgen-stable checkout 1ad769e8602a4973eb2a23efc9707a7da41e96e0
git clone https://github.com/Ignibyte/aic-demo.git lotline
cd lotline
printf 'web_environment:\n  - AIC_APP_SECRET=%s\n' "$(openssl rand -hex 32)" > .ddev/config.local.yaml
ddev start
ddev composer install
ddev exec bin/aic migrate
```

Then make the staff and fill the lot with invented data. Pick your own passwords:

```bash
ddev exec env LOTLINE_ADMIN_EMAIL=admin@example.test LOTLINE_ADMIN_NAME='Ada Admin' \
  LOTLINE_ADMIN_PASSWORD='choose-a-password' LOTLINE_STAFF_PASSWORD='choose-another' bin/aic staff:seed
ddev exec bin/aic inventory:seed
ddev exec bin/aic customers:seed
ddev exec bin/aic sales:seed
```

Open the URL `ddev start` printed, sign in at `/sign-in` with the admin's email and password, and
look around. `/theme` is the style guide, open without signing in. This runs Lotline under PHP
(nginx and php-fpm in DDEV), the runtime to use in production.

### See it work

DDEV runs Lotline with AIC's profiler on (`AIC_PROFILER=1` in `.ddev/config.yaml`). Every page
ends with a small "AIC" bar: click it for the page's status, time, SQL count and size, then filter
the inventory, sort it, change page, save a vehicle or wait on the dashboard, and each request
Tessera sends lists below it with its targets, status, milliseconds and bytes. Each line opens the
request's profile at `/_profiler/{id}`, with its statements (a repeated one is marked), the
templates it rendered and the Tessera headers asked and answered. `/_profiler` lists the newest
hundred requests. To turn the profiler off, delete the `AIC_PROFILER=1` line from
`.ddev/config.yaml` and run `ddev restart`. Production and the FrankenPHP container never set it,
so they record nothing.

### Under FrankenPHP

`deploy/frankenphp/` holds a Caddyfile and a PHP 8.5 container. From `lotline/`:

```bash
docker build --build-context aic=../aic-nextgen-stable -f deploy/frankenphp/Dockerfile -t lotline .
docker run -d --name lotline -p 8080:8080 -e SERVER_NAME=:8080 \
  -e AIC_APP_SECRET="$(openssl rand -hex 32)" \
  -e PGHOST=... -e PGPORT=5432 -e PGDATABASE=... -e PGUSER=... -e PGPASSWORD=... lotline
docker exec lotline bin/aic migrate
```

Run the seeds the same way, with `docker exec lotline` in place of `ddev exec`. AIC's
[deploy page](https://github.com/Ignibyte/aic-nextgen/blob/main/framework/docs/deploy.md) covers
the rest: the schedule, the queue worker and a domain with automatic HTTPS.

### Compiled (experimental)

The same code compiles into one native HTTP server with
[AIC's elephc fork](https://github.com/Ignibyte/aic_compiler): build it as its `FORK.md` says, so
`elephc` is on your `PATH`. This needs PHP 8.5 and Composer on the host too:

```bash
composer install
composer run native:serve        # builds the compiled site and serves it on http://127.0.0.1:8090
```

It finds DDEV's Postgres by itself. The gates hold the compiled site to PHP's answers on every
page, but the compiler is young: try it, compare, and keep PHP for anything real.

## How it is built

This foundation provides the application runtime, database health check, installed development workflow and Lotline's theme. Staff sign in (the `staff` module, below), keep the vehicles on the lot (the `inventory` module), keep the customers (the `customers` module) and record the sales (the `sales` module), and land on the dashboard (the `dashboard` module).

AIC is pinned through the clean sibling checkout `../aic-nextgen-stable` at `1ad769e8602a4973eb2a23efc9707a7da41e96e0`, with Tessera 0.4.1 pinned in `config/app.php`: protocol 2, where a component is marked by `data-t-c` on its one root element and a link, form or poll may name a component by its anchor, `@NAME`. Module code and the browser tests target the inventory results and the dashboard's live part by their anchors (`@inventory-results`, `@dashboard-live`) and spell no component path. That commit brings the guard fields as an atom (AIC #246): every `Form` takes a `GuardFields` from `FormTokens::guard()` in place of a token string. AIC's elephc fork compiles the shapes AIC #248 and #245 named, so the sidebar builds its links with one spread and a bulk edit finds its missing VINs with `array_diff()`. Framework, workflow and gate packages use Composer path repositories. DDEV mounts that checkout read-only at `/var/www/aic-nextgen-stable`.

`config/app.php` names the theme, `themes/lotline/`. Its templates sit on top: `layouts/` (the daisyUI drawer, `html.phtml` and `page.phtml`) and `components/<Name>/` (each component's `.phtml`). The PHP behind them sits in `src/`: `src/Layouts/Page.php`, whose regions are `sidebar`, `header`, `content` and `footer`, and `src/Components/<Name>/`, each component's class and its value classes. `assets/` holds the stylesheet. Modules hold no component, template or asset. The look is old Lotline's: Tailwind 4 and daisyUI 5 with its two themes, dark the default. `themes/lotline/frontend/` holds the Tailwind source and its npm build, which scans the theme's templates and classes and writes `themes/lotline/assets/lotline.css`; after changing a template's classes, run `npm ci && npm run build` there, then `composer run build`, and commit the stylesheet.

`/theme` is the style guide: every part of the theme with sample Ridgeway Motors data. That covers the stat tiles, the column and bar charts, a list with a filter bar, sortable heads, status badges and pages (answered in place by Tessera), a record card, a confirm card, a form whose errors come from a real 422, notices, the account menu and the icons. A feature takes its parts from there. Theme components are typed data; their builders (`Heroicons`, `BarCharts`, `ColumnCharts`) compute icon paths and chart geometry, and templates only print.

Start the interpreted reference and Postgres with `ddev start`. Use `composer run build`, `composer run gate` and `composer run native:serve` from this directory. The build writes everything under `build/`: the generated wiring and escaped templates in `build/generated/`, `build/surface.json`, `build/assets.json`, and `build/assets/`, which AIC serves at `/assets/`. `public/` holds only `index.php`. The compiled site and interpreted PHP serve the same app, and the gates compare the two; PHP is the production runtime for now.

The installed [workflow instructions](AGENTS.md) govern feature work. Read the framework's [architecture guide](vendor/aic/framework/docs/README.md) and [Codex installation guide](vendor/aic/workflow/README.md). Operator review of the exact native hook definitions and `bin/aic workflow:codex-check` are required before agent launch.

`GET /health` checks the actual database. Application-owned [native comparison steps](config/native-site.json) cover bootstrap health and missing routes. Product browser and Tessera coverage is added with each feature.

Staff sign in at `/sign-in`, and everything under `/app` needs a session; `modules/staff/README.md` has the details. After `bin/aic migrate`, `bin/aic staff:seed` saves the admin from `LOTLINE_ADMIN_EMAIL`, `LOTLINE_ADMIN_NAME` and `LOTLINE_ADMIN_PASSWORD`, and four invented `@ridgeway-motors.test` staff when `LOTLINE_STAFF_PASSWORD` is set; on a dev box DDEV's ignored `.ddev/config.local.yaml` holds them. `bin/aic user:add {login} {password}` makes an admin, and is how gate:23 and `vendor/bin/aic-gate browser` make their own `gate` user. The browser tests in `tests/browser/` run in gate:23 and with `vendor/bin/aic-gate browser --base-url=https://aic-demo.ddev.site:8843`.

Signed-in staff see the vehicles on the lot at `/app/inventory`, linked from the sidebar: a table searched, filtered, sorted and paged by plain GET parameters, which Tessera refreshes in place: a filter, a sort or a page swaps the table alone and keeps the URL in step. Each VIN opens the vehicle's page at `/app/inventory/{vin}`, where it is edited or, after a confirmation, deleted (a sold vehicle stays); "Add a vehicle" opens the form. The form checks the VIN's format, check digit and uniqueness, the year, the texts, the numbers, the status and the date, and answers a refusal in place; `modules/inventory/README.md` has the details. After `bin/aic migrate`, `bin/aic inventory:seed` saves 60 invented vehicles with fictional, check-digit-valid VINs, and a rerun updates the same rows. The inventory browser tests compare the list with Postgres through `psql`, so they need the seed in the database gate:23 uses (DDEV's).

Signed-in staff keep Ridgeway Motors' customers at `/app/customers`, linked from the sidebar: a list by name, searched over name, email and phone and paged by plain GET parameters. Each name opens the customer's page at `/app/customers/{id}`, with the vehicles they bought, where it is edited or, after a confirmation, deleted; "Add a customer" opens the form, which needs a name and a valid email no other customer has, and checks the phone when one is given; `modules/customers/README.md` has the details. After `bin/aic migrate`, `bin/aic customers:seed` saves 40 invented customers at `@example.test`, and a rerun updates them by email. The customer browser tests need that seed in DDEV's database too.

Signed-in staff record Ridgeway Motors' sales at `/app/sales`, linked from the sidebar: a list newest first, filtered by month and salesperson by plain GET parameters, with the totals of what the filters keep (how many, the revenue and the gross profit). "Record a sale", there or on a vehicle's page, opens the form: the vehicle, the customer and the salesperson, the price and the day. Recording the sale marks the vehicle sold in the same transaction, and a vehicle is sold once: a second sale is refused in place. Each sale has a page at `/app/sales/{id}`, where it can be voided, which puts the vehicle back in stock. The vehicle's page shows its sale and the customer's page lists what they bought; `modules/sales/README.md` has the details. After `bin/aic migrate` and the staff, inventory and customers seeds, `bin/aic sales:seed` saves 36 invented sold vehicles and 44 invented sales over the twelve months to October 2026, the same on every run. The sales browser tests need those seeds in DDEV's database.

Signed-in staff land on the dashboard at `/app`, the sidebar's Dashboard link: the header greets them by name and role, four stat tiles give the vehicles in stock, this month's sales and revenue (each beside last month's) and the average days on the lot, and three charts drawn as SVG on the server give the sales by month over the last twelve months, the vehicles in stock or on hold by make and each salesperson's revenue over those months. Each chart carries its title and a summary for screen readers; every figure is counted against the database's today. The figures come from views the dashboard's own migration creates, read through read-only entity types; `modules/dashboard/README.md` describes them. The dashboard browser tests compare every tile and bar with Postgres.

The dashboard is live: a "Latest sales" panel lists the five sales recorded last, and it and the tiles follow every sale recorded or voided anywhere, within about five seconds and with no reload. Tessera's `data-poll` asks the dashboard page itself for those two parts every five seconds, each time one ordinary request, and holds nothing open; it skips a tick while the tab is hidden and sends one in twenty while the window has no focus.

The vehicle, customer and sale forms submit through Tessera, which AIC and the pinned release give every form on the site without app code: a refused form comes back in place (422) with its error summary focused and the URL kept, a saved one follows the server's redirect to the record's page without loading a new document, and each write carries a write id, so a double click records one write and a repeat is answered from AIC's ledger. With JavaScript off the same forms post and redirect as plain HTML. Browser tests in `tests/browser/` (`form-*`, `forms-no-script`, `dashboard-poll`, `dashboard-poll-hidden`) hold all of it.

The whole site moves through Tessera's enhanced navigation, again with no app script: every link on the site is a Tessera visit, answered with the layout's regions, so the sidebar stays and the header, the content and the sidebar's current link follow the page; the URL is pushed, the focus goes to the new page's heading, and back and forward fetch each page again and put its scroll back. Each page has its own title, `Customers · Lotline` and so on, in the whole document and in every fragment, so the browser's tab follows too. Sign-out loads a whole document, since it ends the session, and so does the homepage's health link, which answers JSON and says so with `data-enhance="off"`. Every direct visit, and every page with JavaScript off, is still a whole document. Browser tests in `tests/browser/` (`navigation-walk`, `navigation-history`, `navigation-no-script`) walk every page.

Provide `AIC_APP_SECRET` outside version control. Development database credentials come from DDEV or PG environment variables. Product data and seed passwords are never committed.

## License

MIT (`LICENSE`). Ridgeway Motors, its staff, customers and vehicles are invented.
