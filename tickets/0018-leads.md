---
number: 18
title: "Leads: a customer asks about a car, and the salesperson hears of it"
type: feature
status: closed
created: 2026-10-28T12:00:00Z
updated: 2026-10-29T04:53:44Z
phase: "docs"
---

A visitor on a public vehicle page can ask about the car or book a test drive. This is the app's
first background work and its first notifications.

- Two forms on `/cars/{slug}`: "Ask about this car" (name, email, phone optional, message) and
  "Book a test drive" (name, email, phone, a day and a time in opening hours, at least a day
  ahead). Errors answer in place (422); a sent form shows a thank-you in place; a double click
  records one lead (Tessera's write id); with JavaScript off both forms work. The form guard's
  honeypot and token apply to a visitor who never signed in.
- A lead records the vehicle, the kind, what was asked and when, and is assigned to a salesperson in
  turn (round robin over active salespeople). A lead whose email matches a customer is linked to
  that customer.
- Sending a lead queues a job; the job sends a notification to the assigned salesperson, kept in
  the database and sent by mail, and a confirmation mail to the visitor. The request that records
  the lead sends no mail itself. `bin/aic queue:work` delivers them; in DDEV the mail lands in
  Mailpit.
- Staff see `/app/leads`: the open leads, newest first, filtered by kind and by salesperson; a
  lead's page shows it and the car; its salesperson or a manager reassigns or closes it.
- Steps in `config/native-site.json` reach every new route, a refused case included; existing steps
  stay.
- Anything AIC lacks for this is AIC work: ask the operator with `bin/aic workflow:ask`, never work
  around it in the app.

Done looks like:

- `composer run gate` passes with no deferral.
- Browser looks, kept in `tests/browser/`: a visitor's inquiry is thanked in place and appears in
  `/app/leads`; a bad test-drive time is refused in place; a double click records one lead; after
  the worker runs once, Mailpit holds both mails (read through its HTTP API) with the right
  recipients, and the salesperson's notification is stored. They pass against DDEV and in gate:23
  on the compiled site.

When this run completes, stop.

## Comments

