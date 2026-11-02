---
number: 34
title: "The resilience page: what Tessera does when things go wrong"
type: feature
status: open
created: 2026-11-02T18:30:00Z
updated: 2026-11-02T18:30:00Z
---

`/theme` gains a "Resilience" section that shows, live and on purpose, each failure Tessera
handles, so a reviewer can trigger them:

- an expired session: a form whose token the page invalidates first, answered `419`, raising
  `tessera:expired` and its default reload prompt;
- a write whose answer never comes (the server holds it past a timeout), raising
  `tessera:indeterminate` and its "it may have been saved" alert;
- a refused form with several errors and its `data-error-summary`, focused;
- a poll whose endpoint fails, raising `tessera:error`, and recovering when it answers again;
- a protocol mismatch (a `400` with `Tessera-Protocol: 1`) falling back to a full load;
- a `Tessera-Full-Visit` answer, and a redirect off the site.

AIC: each case a small Page in the site module behind the style guide's prefix, none reachable
from product pages, each with its gate:23 step. Each case's browser test asserts the event and the
visible outcome.

Every new behaviour gets a kept browser test in `tests/browser/` (also run with JavaScript off where the feature has a no-script path), and every new GET route a step in `config/native-site.json`, so gate:23 holds it on the compiled site. A defect in AIC or Tessera goes to the operator as an AIC or Tessera ticket, not a workaround here.

## Comments
