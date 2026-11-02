---
number: 32
title: "Live chat between a storefront visitor and the salesperson"
type: feature
status: open
created: 2026-11-02T18:30:00Z
updated: 2026-11-02T18:30:00Z
---

A visitor on `/cars/{vin}` opens a chat about that car; it reaches the salesperson assigned to the
car's leads (or the sales manager), whose inbox shows it live. Both sides see messages appear as
they are sent, a typing indicator, and an unread count; the visitor keeps the chat open while
moving around the storefront.

Tessera API: `data-stream` on both sides; `tessera-patch-elements` in `append` mode for each
message; `tessera-patch-signals` for typing and unread state; `Last-Event-ID` so a dropped
connection resumes without losing a message; `data-permanent` on the visitor's chat dock so it
survives enhanced navigation; `data-ignore-morph` on the message box; `data-on__keydown` (Enter
sends, Shift+Enter breaks the line); a stream pause while the tab is hidden.

AIC: a `chat` module (entity, migration, a public Page and Stream, a staff Stream), the visitor
known by a signed envelope in their session (no account), the form guard on each send, rate
limiting by the session, and a Job emailing the transcript when the visitor leaves an address.
With JavaScript off the chat is a form that posts and reloads.

Every new behaviour gets a kept browser test in `tests/browser/` (also run with JavaScript off where the feature has a no-script path), and every new GET route a step in `config/native-site.json`, so gate:23 holds it on the compiled site. A defect in AIC or Tessera goes to the operator as an AIC or Tessera ticket, not a workaround here.

## Comments
