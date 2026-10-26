# Changelog

## Unreleased

- The rest of Lotline's theming (AIC #128, slice 2): form fields, selects, form grid and actions, filter bar, data table with sortable heads and badge cells, status badges, record and confirm cards, notices, pagination, stat tiles, panels, column and bar charts (old Lotline's geometry, computed by theme builders), the signed-in menu and the remaining Heroicons, all as typed theme components. `/theme` shows them with sample data, its list filtered, sorted and paged in place, its form answered by a real 422. The sidebar links Theme.
- Lotline's theme, `themes/lotline/` (AIC #128): old Lotline's look on current AIC. Its two daisyUI themes (dark the default) and atom styling, built by Tailwind 4 from `frontend/` into the theme's assets; the drawer shell as `.phtml` layouts with `sidebar`, `header`, `content` and `footer` regions; the top bar, sidebar navigation with Heroicons (each a template branch) and the footer as theme components, placed by the site module's page and blocks. AIC moves to main 74cd852; the suite stubs and the Codex workflow pack are current. The site module holds no component, template or asset, and the framework serves assets in place of the module's own controller.
- Bootstrap the fresh Lotline application with reviewed AIC packages and the Codex workflow.
