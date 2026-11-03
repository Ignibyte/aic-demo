---
number: 40
title: "Drop the elephc workarounds the current compiler no longer needs"
type: chore
status: open
created: 2026-11-03T12:00:00Z
updated: 2026-11-03T12:00:00Z
---

The operator probed every comment that shapes Lotline's code around elephc and says no ticket
carries it (2026-11-03, on AIC's fork at dff96a21af). Each shape below now compiles and runs as
PHP does, as a program and as a pool web server, so its workaround goes and the code takes the
plain form the comment describes:

- the two spreads in one form's fields: `VehicleForm` (~69), `CustomerForm` (~71), `PhotoForms`
  (~49). The 500 was only ever seen on the whole compiled site, so gate:23 is the proof here;
- a row's typed reads straight on a `foreach` over `rows()`: `VehicleRows`, `CustomerRows`,
  `SaleRows`, `DashboardRepository`, `VehiclePhotoRepository`, `ExportRepository`,
  `FigureRepository`, `RangeRepository` (AIC #201 made `Rows` typed);
- `stringOrNull() ?? ''` in `LeadRows`;
- an untyped value narrowed by `is_string()`: `StaffRequests`, `VehicleRecords` (~153);
- a nullable id for the caller: `AccountRepository::add()` (~104) returns `?int` again;
- the enum-case ternary in `SeedVehicles` (~166);
- string `<` and `>`: `SaleChecks` (~165), `LeadHours` (~20);
- `tryFrom()` in `ReportKinds`;
- an array element read as mixed: `SettingsSeals` (~156), `ProfileRepository` (~103),
  `ChangeLines` (~44);
- the date classes: `SeedSales` (~26), `MonthLengths` (~10);
- `pow()` in `Payments` (~18).

`NavigationBlock` (~77) stays: it is AIC #248 (a local typed `array<C>` reassigned inside an `if`
from a function declared only `array` is refused); cite #248 in its comment. `VehicleBulkEdits`'
loop stays for AIC #245.

Behaviour does not change: the kept browser looks and gate:23 hold it on both sites. A
workaround that turns out still to be needed stays, and the run says which, with the failing
output, through `workflow:ask`; the operator files the carry.

## Comments
