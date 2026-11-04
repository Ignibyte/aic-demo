---
number: 32
title: "Live chat between a storefront visitor and the salesperson"
type: feature
status: closed
created: 2026-11-02T18:30:00Z
updated: 2026-11-04T11:52:59Z
phase: "docs"
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

### 2026-11-04T08:17:42Z

The operator settled three API questions before the run (AIC 98f7ea4, Tessera 0.4.4):

- The message box uses `data-on:keydown`. The description's `data-on__keydown` was a typo: in
  Tessera's directive grammar `__` starts a modifier and the event name follows a colon.
- The stream pause while the tab is hidden is deferred. Tessera pauses only when the stream's
  response sends `Tessera-Stream-Pause: hidden`, and AIC's `EventStream` gives a Stream no way to
  add that header. The operator files an AIC ticket for it; this run builds without the pause, and
  the pause is follow-up work once AIC ships the header.
- Rate limiting by session is built in the chat module. AIC has no rate limiter, so the module
  counts each session's sends over a window and refuses a send over the limit.

### 2026-11-04T12:10:00Z

What shipped differs from the comment above in two ways. The run moved to AIC b874d98 and
Tessera 0.4.5. The stream pause landed during the run: AIC #259 added
`EventStream::pausedWhileHidden()`, so the visitor's stream closes while the tab is hidden.
AIC #260 made anchor-scoped signal patches land at their path. The box-clear signal here is still
scoped to its region, and tickets/0038 moves it back to the `@chat-send` anchor. Still open from
the seeker, for a later ticket: the staff list reuses neither DataTable nor StatusBadge, and the
chat_list view joins users in raw SQL. A second start in one session opens a second chat and
leaves the first one open; no criterion covers that case yet.
