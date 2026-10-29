---
number: 19
title: "The staff inbox and a live bell"
type: feature
status: in-review
created: 2026-10-28T12:00:00Z
updated: 2026-10-29T09:47:58Z
phase: "docs"
---

Staff now get notifications (run 18). This run shows them.

- The top bar has a bell with the signed-in member's unread count, and a menu of the latest five.
  `/app/inbox` lists every notification, newest first, paged, each linking to what it is about.
  Opening one marks it read; "Mark all read" marks them all. Another member's notifications are
  never shown or changeable (a forged id is 404).
- The bell is live: a Server-Sent Events stream (a Stream route, AIC's `EventStream` speaking
  Tessera's stream protocol) pushes the count and the newest notification as a component when one
  arrives, so a lead sent in one browser lights the bell in a salesperson's open page without a
  reload. The stream resumes from `Last-Event-ID` after a drop, sends a keep-alive while idle, and
  ends cleanly when the member signs out. The dashboard keeps its poll: the app should show both.
- A notification read in one tab updates the count in another on the next event.
- Steps in `config/native-site.json` reach every new route, a refused case included; existing steps
  stay.
- Anything AIC lacks for this is AIC work: ask the operator with `bin/aic workflow:ask`, never work
  around it in the app.

Done looks like:

- `composer run gate` passes with no deferral.
- Browser looks, kept in `tests/browser/`: a lead sent from a visitor page lights a signed-in
  salesperson's bell live and the new notification appears in the menu; opening it marks it read and
  drops the count; a resumed stream sends nothing twice; another member's notification is 404. They
  pass against DDEV and in gate:23 on the compiled site.

When this run completes, stop.

## Comments

