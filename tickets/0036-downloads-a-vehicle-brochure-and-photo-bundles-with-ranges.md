---
number: 36
title: "Downloads: a vehicle brochure and photo bundles, with ranges"
type: feature
status: open
created: 2026-11-02T18:30:00Z
updated: 2026-11-02T18:30:00Z
---

Each vehicle gets a one-page brochure (photos, specs, price, the dealership's details) as a
download, and staff can download all of a vehicle's photos as one ZIP. Both are served by AIC's
Stream kind from private files.

AIC API it must exercise: `FileDownload` from `PrivateFiles` with byte ranges (`206`, `416`),
strong ETags and `If-None-Match`/`If-Range`, `Content-Disposition` with a safe name, the brochure
built by a Job when the vehicle changes and kept in storage, and an owner check (staff only for the
ZIP). gate:23 steps request a range and a conditional GET. The brochure's format (HTML-to-PDF
needs a tool) is decided in the plan: a print-styled HTML page with a download of its own is
acceptable if PDF needs a dependency Chad has not approved.

Every new behaviour gets a kept browser test in `tests/browser/` (also run with JavaScript off where the feature has a no-script path), and every new GET route a step in `config/native-site.json`, so gate:23 holds it on the compiled site. A defect in AIC or Tessera goes to the operator as an AIC or Tessera ticket, not a workaround here.

## Comments
