---
number: 25
title: "Turn on AIC's profiler, and see every Tessera exchange"
type: chore
status: open
created: 2026-11-02T18:30:00Z
updated: 2026-11-02T18:30:00Z
---

Chad, 2026-11-02: a web profiler to see what each Tessera response really was. AIC builds it
(AIC #229 core, #230 pages, #231 toolbar) with Tessera 0.4.0's devtools hooks (Tessera #36).

Take the AIC release that has all three and pin Tessera 0.4.0, then:

- DDEV's dev environment sets `AIC_PROFILER=1` (in `.ddev/config.yaml`'s `web_environment`, so a
  fresh clone has it; the README says how to turn it off); production settings and the FrankenPHP
  container leave it off.
- Walk the site with the toolbar open: a page load, an inventory filter, a sort, a page change, a
  vehicle form refused (422) and saved (redirect), the dashboard's poll, the bell's stream. Each
  must show in the toolbar with its targets, bytes and ms, and open in `/_profiler/{id}`.
- Fix anything in Lotline the profiler shows to be wrong: a duplicated query, a fragment that
  answers more than its target, a poll that answers when nothing changed.
- The README's "Try it" gains a short "See it work" paragraph pointing at the toolbar.

Needs AIC #229, #230, #231 and Tessera #36.

Every new behaviour gets a kept browser test in `tests/browser/` (also run with JavaScript off where the feature has a no-script path), and every new GET route a step in `config/native-site.json`, so gate:23 holds it on the compiled site. A defect in AIC or Tessera goes to the operator as an AIC or Tessera ticket, not a workaround here.

## Comments
