# Lotline

Lotline is a fresh dealership operations application built on AIC for fictional Ridgeway Motors data.

This foundation provides the application runtime, database health check, installed development workflow and Lotline's theme. Staff sign in (the `staff` module, below) and see the vehicles on the lot (the `inventory` module); editing vehicles, customers, sales and the dashboard are built in later feature tickets.

AIC is pinned through the clean sibling checkout `../aic-nextgen-stable` at `b74fb24439cf9b5931de95b35c3cefb6b0bf0213`. Framework, workflow and gate packages use Composer path repositories. DDEV mounts that checkout read-only at `/var/www/aic-nextgen-stable`.

`config/app.php` names the theme, `themes/lotline/`. Its templates sit on top: `layouts/` (the daisyUI drawer, `html.phtml` and `page.phtml`) and `components/<Name>/` (each component's `.phtml`). The PHP behind them sits in `src/`: `src/Layouts/Page.php`, whose regions are `sidebar`, `header`, `content` and `footer`, and `src/Components/<Name>/`, each component's class and its value classes. `assets/` holds the stylesheet. Modules hold no component, template or asset. The look is old Lotline's: Tailwind 4 and daisyUI 5 with its two themes, dark the default. `themes/lotline/frontend/` holds the Tailwind source and its npm build, which scans the theme's templates and classes and writes `themes/lotline/assets/lotline.css`; after changing a template's classes, run `npm ci && npm run build` there, then `composer run build`, and commit the stylesheet.

`/theme` is the style guide: every part of the theme with sample Ridgeway Motors data. That covers the stat tiles, the column and bar charts, a list with a filter bar, sortable heads, status badges and pages (answered in place by Tessera), a record card, a confirm card, a form whose errors come from a real 422, notices, the account menu and the icons. A feature takes its parts from there. Theme components are typed data; their builders (`Heroicons`, `BarCharts`, `ColumnCharts`) compute icon paths and chart geometry, and templates only print.

Start the interpreted reference and Postgres with `ddev start`. Use `composer run build`, `composer run gate` and `composer run native:serve` from this directory. The build writes everything under `build/`: the generated wiring and escaped templates in `build/generated/`, `build/surface.json`, `build/assets.json`, and `build/assets/`, which AIC serves at `/assets/`. `public/` holds only `index.php`. The compiled site is the application runtime; interpreted PHP is its comparison reference.

The installed [workflow instructions](AGENTS.md) govern feature work. Read the framework's [architecture guide](vendor/aic/framework/docs/README.md) and [Codex installation guide](vendor/aic/workflow/README.md). Operator review of the exact native hook definitions and `bin/aic workflow:codex-check` are required before agent launch.

`GET /health` checks the actual database. Application-owned [native comparison steps](config/native-site.json) cover bootstrap health and missing routes. Product browser and Tessera coverage is added with each feature.

Staff sign in at `/sign-in`, and everything under `/app` needs a session; `modules/staff/README.md` has the details. After `bin/aic migrate`, `bin/aic staff:seed` saves the admin from `LOTLINE_ADMIN_EMAIL`, `LOTLINE_ADMIN_NAME` and `LOTLINE_ADMIN_PASSWORD`, and four invented `@ridgeway-motors.test` staff when `LOTLINE_STAFF_PASSWORD` is set; on a dev box DDEV's ignored `.ddev/config.local.yaml` holds them. `bin/aic user:add {login} {password}` makes an admin, and is how gate:23 and `vendor/bin/aic-gate browser` make their own `gate` user. The browser tests in `tests/browser/` run in gate:23 and with `vendor/bin/aic-gate browser --base-url=https://aic-demo.ddev.site:8843`.

Signed-in staff see the vehicles on the lot at `/app/inventory`, linked from the sidebar: a table searched, filtered, sorted and paged by plain GET parameters; `modules/inventory/README.md` has the details. After `bin/aic migrate`, `bin/aic inventory:seed` saves 60 invented vehicles with fictional, check-digit-valid VINs, and a rerun updates the same rows. The inventory browser tests compare the list with Postgres through `psql`, so they need the seed in the database gate:23 uses (DDEV's).

Provide `AIC_APP_SECRET` outside version control. Development database credentials come from DDEV or PG environment variables. Product data and seed passwords are never committed.
