---
number: 16
title: "Vehicle photos: uploads, and files served back"
type: feature
status: closed
created: 2026-10-28T12:00:00Z
updated: 2026-10-29T01:50:53Z
phase: "docs"
---

This run gives a vehicle photos. It is the first time the app takes a file from a browser.

- On a vehicle's page, staff with the sales manager or admin role add photos (JPEG, PNG or WebP,
  up to 8 MB each, up to 12 per vehicle) and delete one. A salesperson sees them but cannot change
  them.
- A file is accepted by its content, never its name or its declared type (AIC's `UploadCheck`).
  A refused file (a text file renamed `.jpg`, one too large, a thirteenth photo) is answered in
  place with the reason, through Tessera, and nothing is stored.
- Photos are kept outside `public/` (under `var/`), each recorded in its own table that refers
  to its vehicle; deleting a vehicle deletes its photos and their files.
- A photo is served by a Stream route as a file download shown inline, with its media type,
  an ETag, and a byte range honoured (`206`, `416` for one past the end). An unknown photo is 404.
- The vehicle page shows the photos in order, the first one as the vehicle's main photo, with an
  `alt` that names the vehicle. Reordering is not part of this run.
- Steps in `config/native-site.json` reach every new route, a refused case included; existing steps
  stay.
- Anything AIC lacks for this is AIC work: ask the operator with `bin/aic workflow:ask`, never work
  around it in the app.

Done looks like:

- `composer run gate` passes with no deferral.
- Browser looks, kept in `tests/browser/`: a photo added appears on the page without a new
  document; a renamed text file is refused in place; a salesperson sees no upload control; a
  photo's URL answers its bytes, and a range request answers `206` with the right bytes. They pass
  against DDEV and in gate:23 on the compiled site.

When this run completes, stop.

## Comments

