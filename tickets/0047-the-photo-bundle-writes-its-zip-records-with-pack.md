---
number: 47
title: "The photo bundle writes its ZIP records with pack()"
type: chore
status: closed
created: 2026-11-05T12:00:00Z
updated: 2026-11-07T16:00:00Z
phase: "docs"
---

`modules/photos/src/Service/StoredZip.php` writes each ZIP record field by field, every integer
little-endian through `chr()` (`short()`, `long()`), because the compiler had no `pack()`.
AIC #266 brings `pack()` and `unpack()` to the compiled site (the elephc fork's stdlib prelude),
so the workaround can go.

Write each record with one `pack()` call: the local file header, the central directory entry and
the end record, with the `v` and `V` codes, and drop `short()` and `long()`. The bundle's bytes
must not change: the kept photo-bundle look (and gate:23, which compares the compiled site with
PHP) holds them, and the archive must still open in `unzip -t`. Update the class docblock and the
photos README, which explain the workaround.
