# Changelog

## Unreleased

- Lotline's theme, `themes/lotline/` (AIC #128): old Lotline's look on current AIC. Its two daisyUI themes (dark the default) and atom styling, built by Tailwind 4 from `frontend/` into the theme's assets; the drawer shell as `.phtml` layouts with `sidebar`, `header`, `content` and `footer` regions; the top bar, sidebar navigation with Heroicons (each a template branch) and the footer as theme components, placed by the site module's page and blocks. AIC moves to main 74cd852; the suite stubs and the Codex workflow pack are current. The site module holds no component, template or asset, and the framework serves assets in place of the module's own controller.
- Bootstrap the fresh Lotline application with reviewed AIC packages and the Codex workflow.
