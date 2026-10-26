# Lotline

Lotline is a fresh dealership operations application built on AIC for fictional Ridgeway Motors data.

This foundation provides the application runtime, database health check, installed development workflow and Lotline's theme. Staff sign-in, inventory, customers, sales and the dashboard are built in later feature tickets.

AIC is pinned through the clean sibling checkout `../aic-nextgen-stable` at `74cd85291e37163c18f44bb4d59bc87464c07145`. Framework, workflow and gate packages use Composer path repositories. DDEV mounts that checkout read-only at `/var/www/aic-nextgen-stable`.

`config/app.php` names the theme, `themes/lotline/`: `layouts/` (the daisyUI drawer: `html.phtml`, `page.phtml` and `Page.php`, whose regions are `sidebar`, `header`, `content` and `footer`), `components/<Name>/` (each component's class and its `.phtml`) and `assets/`. Modules hold no component, template or asset. The look is old Lotline's: Tailwind 4 and daisyUI 5 with its two themes, dark the default. `frontend/` holds the Tailwind source and its npm build, which scans the theme's templates and writes `themes/lotline/assets/lotline.css`; after changing a template's classes, run `npm ci && npm run build` there, then `composer run build`, and commit the stylesheet.

`/theme` is the style guide: every part of the theme with sample Ridgeway Motors data. That covers the stat tiles, the column and bar charts, a list with a filter bar, sortable heads, status badges and pages (answered in place by Tessera), a record card, a confirm card, a form whose errors come from a real 422, notices, the account menu and the icons. A feature takes its parts from there. Theme components are typed data; their builders (`Heroicons`, `BarCharts`, `ColumnCharts`) compute icon paths and chart geometry, and templates only print.

Start the interpreted reference and Postgres with `ddev start`. Use `composer run build`, `composer run gate` and `composer run native:serve` from this directory. The compiled site is the application runtime; interpreted PHP is its comparison reference.

The installed [workflow instructions](AGENTS.md) govern feature work. Read the framework's [architecture guide](vendor/aic/framework/docs/README.md) and [Codex installation guide](vendor/aic/workflow/README.md). Operator review of the exact native hook definitions and `bin/aic workflow:codex-check` are required before agent launch.

`GET /health` checks the actual database. Application-owned [native comparison steps](config/native-site.json) cover bootstrap health and missing routes. Product browser and Tessera coverage is added with each feature.

Provide `AIC_APP_SECRET` outside version control. Development database credentials come from DDEV or PG environment variables. Product data and seed passwords are never committed.
