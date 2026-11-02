---
number: 38
title: "API coverage: every Tessera and AIC feature, shown and held"
type: feature
status: open
created: 2026-11-02T18:30:00Z
updated: 2026-11-02T18:30:00Z
---

Close the expansion by proving coverage. `/theme` gains a "Coverage" page with one row per
Tessera attribute, directive, action, event and runtime member, and one per AIC surface that
`bin/aic code:coverage` (AIC #232) lists: the live example (on the page itself or a link to the
product page that uses it), and the browser test that holds it.

- Every row is green or carries a written reason it is not used (for example SOAP, or Tessera
  helpers if Chad chose none in #26).
- `bin/aic code:coverage --missing` run on Lotline prints nothing but the reasoned rows; the
  ticket's notes paste its output.
- A browser test walks the coverage page and follows every link, so a feature dropped later
  breaks the test.

Needs AIC #232 and tickets #25 to #37.

Every new behaviour gets a kept browser test in `tests/browser/` (also run with JavaScript off where the feature has a no-script path), and every new GET route a step in `config/native-site.json`, so gate:23 holds it on the compiled site. A defect in AIC or Tessera goes to the operator as an AIC or Tessera ticket, not a workaround here.

## Comments
