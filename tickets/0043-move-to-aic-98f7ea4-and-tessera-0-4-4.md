---
number: 43
title: "Move to AIC 98f7ea4 and Tessera 0.4.4: signal patches, prefetchable pages, hover prefetch"
type: chore
status: closed
created: 2026-11-04T00:30:00Z
updated: 2026-11-04T08:12:05Z
phase: "docs"
---

The operator has moved `../aic-nextgen-stable` to AIC `98f7ea45fae3e9698f0ad9fa34b5792a10851ca9`, and has switched the two
pinned-Tessera steps in `config/native-site.json` to asset names
(`{asset:tessera/tessera.csp.iife.js}` and its map, AIC #251). A Tessera pin bump now changes
`config/app.php` and no step. Run `composer update 'aic/*'` (never `--with-dependencies`) and
`composer run build` first.

What the new AIC commit adds, and what Lotline does with it:

- **Signal patches (AIC #253):** `PageDocument::withSignalPatch(new SignalPatch($scope, $json,
  $onlyIfMissing))` adds a `<script type="application/json" data-scope>` row to a page's fragment
  answer, `data-only-if-missing` included. #31 dropped `data-only-if-missing` because AIC had no way
  to send it. Use it where a fragment answer should set a signal: the vehicle form's refusals are one
  candidate. Keep it small. #38 shows the full set.
- **Prefetchable pages (AIC #254):** `PageDocument::prefetchable()` answers `private, no-cache`, so
  Tessera uses a hover prefetch's answer. The vehicle page opts in, and each inventory row's VIN link
  gets `data-prefetch="hover"` again. #31's criterion 12 was the press prefetch alone (decision 17).
  The look proves the hover sends one request and the click paints from it, with no second request.
- **Shared handler services (AIC #257)** and **gate:10's fresh PDepend cache (AIC #256):** nothing
  to change. The build output moves with them.
- **Tessera 0.4.4:** pin it in `config/app.php` with the release's SHA-256 sums:
  - `tessera.csp.iife.js` `e2bdd4b39638468f498cd97d4a1865f250960e9dd5be3c339ceb0c40db7560c5`
  - `tessera.csp.iife.js.map` `6ee44d291c9007b18aadd05155b2f21f8df0340be9e42a9460f972cc036afb72`

  Between 0.4.2 and 0.4.4:
  - a keyboard focus prefetches as a hover does;
  - a press drops a pending hover timer, so a click never sends two requests;
  - a `data-on:dragstart` drag starts in Firefox, which helps the leads board (#30);
  - Back to a page whose recorded target is gone loads the whole page.

The README's two pin lines name the full AIC hash and Tessera 0.4.4, and say what each brings.
Behaviour holds everywhere else. Every kept look passes, and the new hover look is kept.
