---
number: 34
title: "The resilience page: what Tessera does when things go wrong"
type: feature
status: closed
created: 2026-11-02T18:30:00Z
updated: 2026-11-04T15:44:56Z
phase: "docs"
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

### 2026-11-04T14:16:40Z

The operator checked the API before the run (Tessera 0.4.5):

- Tessera 0.4.5 has `tessera:expired`, `tessera:indeterminate`, `tessera:error` and
  `data-error-summary`.
- Tessera sets no client timeout on a write. An indeterminate outcome comes from a write whose
  connection fails after it is sent, or whose answer Tessera cannot use. So the "answer never
  comes" case is built as what a gateway sends after its own timeout: a `504` with a
  `text/plain` body, answering a write. Nothing holds a worker open on the compiled site.
- The redirect off the site is out of scope for this ticket. AIC's `PageRedirect` takes only a
  path on this site, by design (AIC #207). The case is left out and the rest is built. A
  redirect to another origin is a possible AIC feature.
- Every case page sits behind the style guide's prefix with a gate:23 step, and no product page
  links to one.
