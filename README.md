# Lotline

Lotline is the demo site for [AIC](https://github.com/Ignibyte/aic-nextgen), a PHP 8.5 framework
built for coding agents to write apps in and built to compile to a native binary with elephc. It
is a dealership's back office for the invented Ridgeway Motors: staff sign in, keep the vehicles
on the lot, the customers and the sales, and land on a live dashboard. A coding agent wrote it
through AIC's workflow, one ticket at a time (`tickets/`).

## Install

You need git, Docker and [DDEV](https://ddev.com) 1.24.10 or later. DDEV brings PHP 8.5,
Composer and Postgres 17; AIC itself comes from Packagist, and no `vendor/` is committed.

### With DDEV, in one command

```bash
git clone https://github.com/Ignibyte/aic-demo.git lotline
cd lotline
ddev install
```

`ddev install` (`.ddev/commands/host/install`) does the whole install and is safe to run again:

1. It writes the settings that are missing to `.ddev/config.local.yaml`, which git ignores: a
   random app secret, the admin's sign-in (`admin@example.test`, a random password), a random
   password for the four invented staff, and the site URL. It never changes a value already there.
2. It restarts DDEV so the web container reads them, then runs `composer install`,
   `bin/aic migrate` and the four seeds: staff, 60 vehicles, 40 customers and 44 sales, all
   invented. A rerun updates the same rows.
3. It checks that `/health` answers 200 and prints the sign-in URL, the admin's email and password,
   and the staff password.

Sign in at the URL it prints. `/theme` is the style guide, open without signing in. This runs
Lotline under PHP (nginx and php-fpm in DDEV), the runtime to use in production.

### Step by step

The same install by hand, with passwords you choose:

```bash
git clone https://github.com/Ignibyte/aic-demo.git lotline
cd lotline
cat > .ddev/config.local.yaml <<YAML
web_environment:
  - AIC_APP_SECRET=$(openssl rand -hex 32)
  - LOTLINE_ADMIN_EMAIL=admin@example.test
  - LOTLINE_ADMIN_NAME=Ada Admin
  - LOTLINE_ADMIN_PASSWORD=choose-a-password
  - LOTLINE_STAFF_PASSWORD=choose-another
YAML
ddev start
ddev composer install
ddev exec bin/aic migrate
for seed in staff inventory customers sales; do ddev exec bin/aic "$seed:seed"; done
```

Then open the URL `ddev start` printed and sign in at `/sign-in`.

### For a coding agent

If you are an agent asked to install or run Lotline, follow this and nothing else:

1. Check the tools: `git --version`, `docker info` and `ddev version` (1.24.10 or later) must all
   succeed. If DDEV is missing, stop and ask; do not install system packages yourself.
2. From the clone's root, run `ddev install`. It takes a few minutes the first time, while DDEV
   pulls its images and Composer downloads the packages.
3. It succeeded when its last lines begin `Lotline is installed.` and print the sign-in URL and
   the admin's email and password; it has already checked that `/health` answers 200.
4. On a failure, read its last lines. A port already in use means another DDEV project or web
   server holds 80 or 443: `ddev poweroff` stops every DDEV project, then rerun `ddev install`.
   Rerunning is always safe.
5. Do not edit `vendor/`, `build/`, or the files `ignibyte/aic-scaffold` writes on every install
   (`public/index.php`, `bootstrap/serve.php`, `bin/aic`). The settings and passwords are in
   `.ddev/config.local.yaml`; never commit that file.
6. To work on Lotline itself, read [AGENTS.md](AGENTS.md): changes go through AIC's workflow,
   one ticket at a time.

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
docker build -f deploy/frankenphp/Dockerfile -t lotline .
docker run -d --name lotline -p 8080:8080 -e SERVER_NAME=:8080 \
  -e AIC_APP_SECRET="$(openssl rand -hex 32)" \
  -e PGHOST=... -e PGPORT=5432 -e PGDATABASE=... -e PGUSER=... -e PGPASSWORD=... lotline
docker exec lotline bin/aic migrate
```

Run the seeds the same way, with `docker exec lotline` in place of `ddev exec`. AIC's
[deploy page](https://github.com/Ignibyte/aic-nextgen/blob/main/framework/docs/deploy.md) covers
the rest: the schedule, the queue worker and a domain with automatic HTTPS.

### Compiled on Linux (experimental)

The same code also compiles into one native HTTP server, a single Linux executable, with
[AIC's elephc fork](https://github.com/Ignibyte/aic_compiler). On Linux x86_64,
`vendor/bin/aic-gate tools` downloads the compiler release AIC pins and checks its SHA-256;
anywhere else, build the fork from source as its `FORK.md` says and put `elephc` on your `PATH`.
It runs on the host, beside DDEV, and needs PHP 8.5, Composer, a C toolchain (`build-essential`)
and the bzip2 and zlib development libraries there:

```bash
composer install
vendor/bin/aic-gate tools
composer run native:serve        # compiles the site and serves it on http://127.0.0.1:8090
```

It finds DDEV's Postgres by itself. The binary it builds is `.native-site/main`, run from the
clone's root as `.native-site/main --listen 127.0.0.1:8090`, with the same `AIC_APP_SECRET` and
`PG*` settings in its environment; it serves the files in `build/assets/` from disk, so keep it
beside the clone. The gates hold the compiled site to PHP's answers on every page, but the
compiler is young: try it, compare, and keep PHP for anything real. `bin/aic` (the migrations,
the seeds, the queue worker) runs under PHP either way.

## How it is built

This foundation provides the application runtime, database health check, installed development workflow and Lotline's theme. Staff sign in (the `staff` module, below), keep the vehicles on the lot (the `inventory` module), keep the customers (the `customers` module) and record the sales (the `sales` module), and land on the dashboard (the `dashboard` module).

Lotline is an AIC app as `composer create-project ignibyte/aic-project` makes one. It requires `ignibyte/aic-framework` at `^0.1` from Packagist, with `ignibyte/aic-gates`, `ignibyte/aic-workflow` and `ignibyte/aic-scaffold` for development, and commits no `vendor/`. On every `composer install` and `update`, `ignibyte/aic-scaffold` writes the framework's entry files (`public/index.php`, `bootstrap/serve.php`, `bin/aic`), adds the config starters it is missing and runs `aic-gate init`; edit Lotline's own config files, never those three. `build/` is committed, so a deploy needs `composer install --no-dev` and no build step. Its changelog records which AIC each change was built on.

`config/app.php` names the theme, `themes/lotline/`. Its templates sit on top: `layouts/` (the daisyUI drawer, `html.phtml` and `page.phtml`) and `components/<Name>/` (each component's `.phtml`). The PHP behind them sits in `src/`: `src/Layouts/Page.php`, whose regions are `sidebar`, `header`, `content` and `footer`, and `src/Components/<Name>/`, each component's class and its value classes. `assets/` holds the stylesheet. Modules hold no component, template or asset. The look is old Lotline's: Tailwind 4 and daisyUI 5 with its two themes, dark the default. `themes/lotline/frontend/` holds the Tailwind source and its npm build, which scans the theme's templates and classes and writes `themes/lotline/assets/lotline.css`; after changing a template's classes, run `npm ci && npm run build` there, then `composer run build`, and commit the stylesheet.

`/theme` is the style guide: every part of the theme with sample Ridgeway Motors data. That covers the stat tiles, the column and bar charts, a list with a filter bar, sortable heads, status badges and pages (answered in place by Tessera), a record card, a confirm card, a form whose errors come from a real 422, notices, the account menu and the icons. A feature takes its parts from there. Theme components are typed data; their builders (`Heroicons`, `BarCharts`, `ColumnCharts`) compute icon paths and chart geometry, and templates only print.

Start the interpreted reference and Postgres with `ddev start`. `.ddev/docker-compose.mailpit-smtp.yaml` publishes the web container's Mailpit SMTP on a free `127.0.0.1` port: the browser looks run `$AIC_PHP bin/aic queue:work` (and the scheduler) on the host, with the PHP and database settings gate:23 hands them, and point its mail at that port, read from `ddev describe -j`, so it lands in DDEV's Mailpit, where the looks read it. Run `ddev restart` once after pulling that file. Use `composer run build`, `composer run gate` and `composer run native:serve` from this directory. The build writes everything under `build/`: the generated wiring and escaped templates in `build/generated/`, `build/surface.json`, `build/assets.json`, and `build/assets/`, which AIC serves at `/assets/`. `public/` holds only `index.php`. The compiled site and interpreted PHP serve the same app, and the gates compare the two; PHP is the production runtime for now.

The installed [workflow instructions](AGENTS.md) govern feature work. Read the framework's [architecture guide](vendor/ignibyte/aic-framework/docs/README.md) and [Codex installation guide](vendor/ignibyte/aic-workflow/README.md). Operator review of the exact native hook definitions and `bin/aic workflow:codex-check` are required before agent launch.

`GET /health` checks the actual database. Application-owned [native comparison steps](config/native-site.json) cover bootstrap health and missing routes. Product browser and Tessera coverage is added with each feature.

Staff sign in at `/sign-in`, and everything under `/app` needs a session; `modules/staff/README.md` has the details. After `bin/aic migrate`, `bin/aic staff:seed` saves the admin from `LOTLINE_ADMIN_EMAIL`, `LOTLINE_ADMIN_NAME` and `LOTLINE_ADMIN_PASSWORD`, and four invented `@ridgeway-motors.test` staff when `LOTLINE_STAFF_PASSWORD` is set; on a dev box DDEV's ignored `.ddev/config.local.yaml` holds them. `bin/aic user:add {login} {password}` makes an admin, and is how gate:23 and `vendor/bin/aic-gate browser` make their own `gate` user. The browser tests in `tests/browser/` run in gate:23 and with `vendor/bin/aic-gate browser --base-url=https://aic-demo.ddev.site:8843`.

Signed-in staff see the vehicles on the lot at `/app/inventory`, linked from the sidebar: a table searched, filtered, sorted and paged by plain GET parameters, which Tessera refreshes in place: a filter, a sort or a page swaps the table alone and keeps the URL in step. Each VIN opens the vehicle's page at `/app/inventory/{vin}`, where it is edited or, after a confirmation, deleted (a sold vehicle stays); "Add a vehicle" opens the form. The form checks the VIN's format, check digit and uniqueness, the year, the texts, the numbers, the status and the date, and answers a refusal in place; `modules/inventory/README.md` has the details. After `bin/aic migrate`, `bin/aic inventory:seed` saves 60 invented vehicles with fictional, check-digit-valid VINs, and a rerun updates the same rows. The inventory browser tests compare the list with Postgres through `psql`, so they need the seed in the database gate:23 uses (DDEV's).

Signed-in staff keep Ridgeway Motors' customers at `/app/customers`, linked from the sidebar: a list by name, searched over name, email and phone and paged by plain GET parameters. Each name opens the customer's page at `/app/customers/{id}`, with the vehicles they bought, where it is edited or, after a confirmation, deleted; "Add a customer" opens the form, which needs a name and a valid email no other customer has, and checks the phone when one is given; `modules/customers/README.md` has the details. After `bin/aic migrate`, `bin/aic customers:seed` saves 40 invented customers at `@example.test`, and a rerun updates them by email. The customer browser tests need that seed in DDEV's database too.

Signed-in staff record Ridgeway Motors' sales at `/app/sales`, linked from the sidebar: a list newest first, filtered by month and salesperson by plain GET parameters, with the totals of what the filters keep (how many, the revenue and the gross profit). "Record a sale", there or on a vehicle's page, opens the form: the vehicle, the customer and the salesperson, the price and the day. Recording the sale marks the vehicle sold in the same transaction, and a vehicle is sold once: a second sale is refused in place. Each sale has a page at `/app/sales/{id}`, where it can be voided, which puts the vehicle back in stock. The vehicle's page shows its sale and the customer's page lists what they bought; `modules/sales/README.md` has the details. After `bin/aic migrate` and the staff, inventory and customers seeds, `bin/aic sales:seed` saves 36 invented sold vehicles and 44 invented sales over the twelve months to October 2026, the same on every run. The sales browser tests need those seeds in DDEV's database.

Signed-in staff land on the dashboard at `/app`, the sidebar's Dashboard link: the header greets them by name and role, four stat tiles give the vehicles in stock, this month's sales and revenue (each beside last month's) and the average days on the lot, and three charts drawn as SVG on the server give the sales by month over the last twelve months, the vehicles in stock or on hold by make and each salesperson's revenue over those months. Each chart carries its title and a summary for screen readers; every figure is counted against the database's today. The figures come from views the dashboard's own migration creates, read through read-only entity types; `modules/dashboard/README.md` describes them. The dashboard browser tests compare every tile and bar with Postgres.

The dashboard is live: a "Latest sales" panel lists the five sales recorded last, and it and the tiles follow every sale recorded or voided anywhere, within about five seconds and with no reload. Tessera's `data-poll` asks the dashboard page itself for those two parts every five seconds, each time one ordinary request, and holds nothing open; it skips a tick while the tab is hidden and sends one in twenty while the window has no focus.

The vehicle, customer and sale forms submit through Tessera, which AIC and the pinned release give every form on the site without app code: a refused form comes back in place (422) with its error summary focused and the URL kept, a saved one follows the server's redirect to the record's page without loading a new document, and each write carries a write id, so a double click records one write and a repeat is answered from AIC's ledger. With JavaScript off the same forms post and redirect as plain HTML. Browser tests in `tests/browser/` (`form-*`, `forms-no-script`, `dashboard-poll`, `dashboard-poll-hidden`) hold all of it.

The whole site moves through Tessera's enhanced navigation, again with no app script: every link on the site is a Tessera visit, answered with the layout's regions, so the sidebar stays and the header, the content and the sidebar's current link follow the page; the URL is pushed, the focus goes to the new page's heading, and back and forward fetch each page again and put its scroll back. Each page has its own title, `Customers · Lotline` and so on, in the whole document and in every fragment, so the browser's tab follows too. Sign-out loads a whole document, since it ends the session, and so does the homepage's health link, which answers JSON and says so with `data-enhance="off"`. Every direct visit, and every page with JavaScript off, is still a whole document. Browser tests in `tests/browser/` (`navigation-walk`, `navigation-history`, `navigation-no-script`) walk every page.

Admins see AIC's queue at `/app/jobs`, linked from the sidebar for them alone: what waits, runs or retries, with its attempts and when it is due next, and what failed, with the reason, both newest first and refreshed every five seconds by `data-poll`. A failed job's Retry now queues it again with no attempts and Discard drops it; from the console `bin/aic queue:failed` lists the failed jobs and `bin/aic queue:retry <id>` replays one. Workers run `bin/aic queue:work`, and `bin/aic schedule:run`, once a minute, queues the schedules, such as the nightly digest. To see the retry path on a dev box, run `ddev queue-work-mail-down [max]`: the worker in DDEV's web container with the mail server out of reach, so each mail it takes retries after 30 seconds and fails on its fifth attempt; a plain `ddev exec bin/aic queue:work` delivers again. It is the only fault switch, and nothing outside `.ddev/` reads it. `modules/jobs/README.md` has the details.

Provide `AIC_APP_SECRET` outside version control. Development database credentials come from DDEV or PG environment variables. Product data and seed passwords are never committed.

## License

MIT (`LICENSE`). Ridgeway Motors, its staff, customers and vehicles are invented.
