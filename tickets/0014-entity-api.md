---
number: 14
title: "Every table through the entity API; dashboard parts built from sources, idle polls 204"
type: chore
status: open
created: 2026-10-27T18:00:00Z
updated: 2026-10-27T18:00:00Z
---

AIC moved under this branch (operator, before the run) to the commit that holds its tables to
their modules (AIC #185, #186), builds a part from its source only when it is rendered (AIC #192)
and answers an unchanged poll with 204 (AIC #193). From here gate:9 refuses any app class that
depends on `AIC\Database\Contracts\Database`, and gate:26 refuses an entity type or an alteration that names
a table its module's migrations do not create. Old Lotline has no counterpart; AIC's
`framework/docs/systems.md` and `modules.md` state the API.

- Every repository reads and writes through `EntityStore` and `EntityQuery`: conditions, search,
  paging, `forUpdate`, `first`, totals (`count`, `sum`, `max`, `values`, `countBy`, `sumBy`),
  changes by query, `insertIfAbsent`, `upsert`, `transaction`, and joins through `Reference`
  fields (`with()`). A table another module owns is reached through that module's exported
  repository or entity type, required and exported as gate:26 says.
- The dashboard's reports (months, makes, salespeople, the tiles) become views its own migrations
  create with `Schema::view()`, read through read-only entity types; their SQL lives only there.
- Health and version reads go through `AIC\Database\Application\DatabaseStatus`.
- Every part a Tessera request targets alone (the inventory results, each dashboard part) is a
  `SourcedComponent` inside its `KeyedComponent`: a module service implementing `ComponentSource`
  holds what it needs and reads in `build()`, so a refresh of one part reads only its data
  (`framework/docs/rendering.md`, "Blocks and keys"). Props stay verified in the controller.
- Each polled part seals a `version` in its props (a count and the latest timestamps), and its
  controller answers `PageUnchanged` when `TesseraProps::unchanged()` holds, before it builds
  anything (`framework/docs/tessera.md`).
- Steps in `config/native-site.json` are added for anything new; existing steps stay.
- Anything the entity API cannot express is AIC work: ask the operator, never work around it.

Done looks like:

- `composer run gate` passes with no deferral: no app class depends on `Database`, and every
  table is named only by its own module.
- Every kept browser test passes against DDEV and in gate:23 on the compiled site; the pages
  show what they showed before.
- A kept browser test shows an idle dashboard poll answered 204, and one part's refresh leaves
  the other parts as they were.

When this run completes, stop.

## Comments
