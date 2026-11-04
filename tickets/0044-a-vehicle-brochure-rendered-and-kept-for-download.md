---
number: 44
title: "A vehicle brochure, rendered and kept for download"
type: feature
status: open
created: 2026-11-04T17:40:00Z
updated: 2026-11-04T17:40:00Z
---

Split from tickets/0036, which built the staff photo ZIP. Each vehicle gets a one-page brochure:
its photos, specs and price, and the dealership's details. It is a print-styled HTML page. A Job
renders it when the vehicle changes and keeps it in PrivateFiles, and visitors download it from
the vehicle page through AIC's FileDownload, with byte ranges, a strong ETag and a safe
`Content-Disposition` name. No PDF dependency is approved, so the brochure is HTML that the
browser can print.

Needs AIC #265. AIC lets only its response writer read rendered HTML, and a Page cannot answer
as an attachment, so the app cannot store a rendered page on its own.

Every new behaviour gets a kept browser test in `tests/browser/` (also run with JavaScript off where the feature has a no-script path), and every new GET route a step in `config/native-site.json`, so gate:23 holds it on the compiled site. A defect in AIC or Tessera goes to the operator as an AIC or Tessera ticket, not a workaround here.

## Comments
