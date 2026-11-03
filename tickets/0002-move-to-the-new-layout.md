---
number: 2
title: "Move Lotline to AIC's new layout: theme src/, theme frontend/, build/"
type: chore
status: closed
created: 2026-10-26T20:07:44Z
updated: 2026-10-26T21:32:55Z
phase: "docs"
---

AIC moved where an app keeps things (AIC #167), and this app still has the old layout. Move it,
changing no behaviour: every page, asset and response stays the same.

The layout to reach:

- `themes/lotline/components/<Name>/` and `themes/lotline/layouts/` hold only `.phtml` templates.
- Every theme PHP class moves to `themes/lotline/src/`: each component's class and its value
  classes to `src/Components/<Name>/`, and `Page.php` to `src/Layouts/`. Class names and
  namespaces do not change. Composer's PSR-4 lines and phpcs.xml.dist's theme roots point at
  `src/Components/` and `src/Layouts/`; `composer run build` prints the lines it wants.
- The root `frontend/` (the Tailwind build) moves into the theme as `themes/lotline/frontend/`.
  Its build still writes `themes/lotline/assets/lotline.css`; fix its relative paths
  (`package.json`'s output, `lotline.css`'s `@source` lines) and the ignore line for
  `node_modules/`. Rebuild the CSS there and confirm it is byte for byte the same.
- Everything the build writes lives under `build/`: `composer run build` now writes
  `build/generated/` and `build/assets/`. The old root `generated/` and `public/assets/` go;
  `public/` keeps only `index.php`. composer.json maps `App\Generated\` to `build/generated/`.
- `vendor/bin/aic-gate init` brings the suite stubs up to date; keep what it writes.
- Update `README.md`, `modules/site/README.md` and `CHANGELOG.md` for the new places.

Done looks like:

- `composer run gate` passes with no deferral.
- `/`, `/theme` (with a filter, a sort and a page) and `/health` answer as before on DDEV
  (https://aic-demo.ddev.site:8843) and on the compiled site; look at both in a browser.
- `git status` shows no file left in an old place: no `generated/`, no `public/assets/`, no root
  `frontend/`, no `.php` under `themes/lotline/components/` or `themes/lotline/layouts/`.

Do not start any product feature. When this run completes, stop: the operator and Chad check the
file locations before the next ticket.

## Comments
