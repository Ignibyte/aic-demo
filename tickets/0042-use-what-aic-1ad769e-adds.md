---
number: 42
title: "Use what AIC 1ad769e adds: guard fields, page scripts, removal, aligned tables, the worker in looks"
type: chore
status: closed
created: 2026-11-03T13:30:00Z
updated: 2026-11-03T22:39:11Z
phase: "docs"
---

AIC `1ad769e` (pinned by #41) adds what Lotline had worked around:

- **AIC #246, guard fields:** the theme components that write their own `<form>` for Tessera
  attributes (`BulkBar`, `LeadCard`, any other) place `<?= $x->guardFields ?>` instead of copying the
  token input and the honeypot. The lead card's copy lacks the honeypot today.
- **AIC #239, page scripts:** `themes/lotline/assets/helpers.js` becomes `helpers.page.js`; the
  layouts drop their hand-written `<script defer src="/assets/themes/lotline/helpers.js">`, and the
  script loads tracked and fingerprinted after Tessera.
- **AIC #247, removal:** a bulk delete's deleted rows leave the page (a refresh, submit or action
  answers a gone component's target with mode `remove`) rather than coming back marked "Deleted".
- **AIC #240, aligned columns:** the no-script payment table uses the `Table` atom with
  `TableAlign::End` on its money columns, instead of a hand-written theme component.
- **AIC #225, the worker in looks:** the browser tests run `$AIC_PHP bin/aic queue:work` (gate:23 and
  `aic-gate browser` hand them `AIC_PHP` and the site's database settings) instead of `ddev exec`,
  so they see a job's effect on the compiled site in gate:23 too. Mail stays read from Mailpit.

Each change keeps or tightens a look; the deleted-row look checks the rows are gone.

## Comments
