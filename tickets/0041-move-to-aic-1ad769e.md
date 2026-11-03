---
number: 41
title: "Move to AIC 1ad769e: the guard fields, and the compiler fixes for #248 and #245"
type: chore
status: open
created: 2026-11-03T13:30:00Z
updated: 2026-11-03T13:30:00Z
---

The operator moved `../aic-nextgen-stable` from `cb07056` to `1ad769e` (AIC #239, #240, #246,
#247, #225, #249, #217) and the gate's elephc to AIC's fork at `592b3403e7` (AIC #248, #245, #235).
Nothing to commit for the move itself; this ticket makes Lotline build and pass on it:

- **Breaking, AIC #246:** `Form` takes an `AIC\Component\Domain\GuardFields` in place of its token
  string. Each `new Form(…, $this->formTokens->issue($serverRequest)->value())` becomes
  `new Form(…, $this->formTokens->guard($serverRequest))`; a token held in a variable for several
  forms becomes one `GuardFields`.
- **AIC #248:** `NavigationBlock` builds its links with one spread again; the loop and its comment go.
- **AIC #245:** `VehicleBulkEdits::missing()` uses `array_diff()` again; the `in_array()` loop and its
  comment go.
- README: the pin lines name `1ad769e3` and what it brings.

Behaviour does not change: the kept looks and gate:23 hold it on both sites. A compiler or
framework defect found on the way goes through `workflow:ask`; the operator files it in AIC.

## Comments
