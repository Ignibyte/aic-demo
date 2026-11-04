---
number: 36
title: "Downloads: a vehicle brochure and photo bundles, with ranges"
type: feature
status: closed
created: 2026-11-02T18:30:00Z
updated: 2026-11-04T17:28:18Z
phase: "docs"
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

### 2026-11-04T15:49:23Z

Operator notes before the run:

- The brochure: no PDF dependency is approved. Build a print-styled HTML brochure. The brochure
  Job writes it to `PrivateFiles`, and `FileDownload` serves it as an `.html` attachment.
- The ZIP: check what elephc compiles before designing it. Probe `ZipArchive`, `crc32` and
  `pack` under gate:21. If a class the design needs does not compile natively, ask the operator
  before building round it.
- Ranges, `If-Range` and the ETag come from AIC's `FileDownload`. Keep them out of app code.
- #35 waits on an AIC queue read model, which the operator is building now.
