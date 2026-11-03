---
number: 39
title: "Drop the elephc workarounds whose carries have landed"
type: chore
status: closed
created: 2026-11-03T10:50:00Z
updated: 2026-11-03T12:07:55Z
phase: "docs"
---

Two compiler carries that Lotline worked around are in AIC's elephc fork and the gate's
compiler, so their workarounds go:

- AIC #164 (a `match` whose arms are enum cases): `VehicleStatus`, `VehicleLocation`,
  `VehicleStatuses`, `Role`, `SeedVehicles`, `ThemeSampleStock` and `VehiclePhotos` lower it as
  `if`/`return` "until #164"; write each as the `match` it stands for, and drop the module README
  lines that cite it.
- AIC #244 (a keyed array read as a list after a ternary in `try`): `VehicleEntries::query()`
  splits the list's query with string functions; read it through AIC's `InputParser::fields()`
  again, keeping its string values, and drop the comment.

Behaviour does not change: the kept browser looks and gate:23 hold it on both sites. A
workaround that turns out still to be needed (the compiled site differs, or gate:21 refuses)
stays, and the run says which, with the failing output, through `workflow:ask`.

The other elephc workarounds (`array_diff()` in `VehicleBulkEdits`, AIC #245, and those whose
comment says no ticket carries them yet) stay; the operator is filing carries for them.

## Comments
