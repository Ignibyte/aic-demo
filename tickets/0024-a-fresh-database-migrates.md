---
number: 24
title: "A fresh database migrates"
type: bug
status: closed
created: 2026-11-02T16:00:00Z
updated: 2026-11-02T19:00:00Z
phase: "docs"
---

Found by the public-release dry run (AIC #222): on an empty database, `bin/aic migrate` stopped at
`2026_10_28_000001_create_vehicle_photos` with SQLSTATE 42P01. AIC applied migrations in module
order, and the photos module requires only staff, so its migration ran before inventory's
`create_vehicles`, whose table its foreign key names. The dev database never showed it: its tables
arrived one run at a time, and gate:23 reuses it.

No app-side fix fit AIC's rules (a table belongs to the module that creates it, and photos cannot
require inventory without a cycle). Chad chose AIC's option A: AIC #228 (PR #176, `59704f7`) applies
every module's migrations in version order, and gate:26's R7 refuses a migration that names a table
before the version creating it. Lotline's code is unchanged; the operator moved the AIC pin.

Shown: all 25 migrations apply on an empty database from a clean clone of the README's steps; the
dev database reports every migration applied; gate:26 holds under R7; `composer run gate` passes.
