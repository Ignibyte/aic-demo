# Lotline

Lotline is a fresh dealership operations application built on AIC for fictional Ridgeway Motors data.

This bootstrap provides the application runtime, database health check and installed development workflow. Staff sign-in, inventory, customers, sales and the dashboard are built in later feature tickets. No old Lotline source is included.

AIC is pinned through the clean sibling checkout `../aic-nextgen-stable` at `7083124ba5ed83c7d4893449865bec7ca9b56638`. Framework, workflow and gate packages use Composer path repositories. DDEV mounts that checkout read-only at `/var/www/aic-nextgen-stable`.

Start the interpreted reference and Postgres with `ddev start`. Use `composer run build`, `composer run gate` and `composer run native:serve` from this directory. The compiled site is the application runtime; interpreted PHP is its comparison reference.

The installed [workflow instructions](AGENTS.md) govern feature work. Read the framework's [architecture guide](vendor/aic/framework/docs/README.md) and [Codex installation guide](vendor/aic/workflow/README.md). Operator review of the exact native hook definitions and `bin/aic workflow:codex-check` are required before agent launch.

`GET /health` checks the actual database. Application-owned [native comparison steps](config/native-site.json) cover bootstrap health and missing routes. Product browser and Tessera coverage is added with each feature.

Provide `AIC_APP_SECRET` outside version control. Development database credentials come from DDEV or PG environment variables. Product data and seed passwords are never committed.
