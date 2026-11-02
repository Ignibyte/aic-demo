---
number: 37
title: "A vehicle feed in XML for listing sites"
type: feature
status: open
created: 2026-11-02T18:30:00Z
updated: 2026-11-02T18:30:00Z
---

Listing sites take a dealer's stock as an XML feed. Add `/feeds/vehicles.xml` beside the existing
JSON `/api/vehicles`: every vehicle in stock with its VIN, year, make, model, trim, mileage, price,
photo URLs and the vehicle page's URL, answered by AIC's Api kind with the new `XmlResponse`
(AIC #233), built from typed data, cached with an ETag, and checked against a small XSD in the
repo by a browser-free test step in gate:23.

Needs AIC #233.

Every new behaviour gets a kept browser test in `tests/browser/` (also run with JavaScript off where the feature has a no-script path), and every new GET route a step in `config/native-site.json`, so gate:23 holds it on the compiled site. A defect in AIC or Tessera goes to the operator as an AIC or Tessera ticket, not a workaround here.

## Comments
