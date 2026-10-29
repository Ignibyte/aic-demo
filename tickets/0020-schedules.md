---
number: 20
title: "Schedules: aged stock, holds that expire, a nightly digest"
type: feature
status: in-review
created: 2026-10-28T12:00:00Z
updated: 2026-10-29T11:01:56Z
phase: "docs"
---

This run adds work nobody asks for: the app's first schedules.

- A vehicle can be put on hold for a customer by a salesperson, with the customer and a note; a held
  vehicle leaves the public list and its page (404) and cannot be sold to anyone else. A hold expires
  48 hours after it was placed.
- Schedules, declared in the manifests and run by `bin/aic schedule:run` once a minute:
  - every 15 minutes, expired holds are released (the vehicle is for sale again) and the salesperson
    who placed each is notified;
  - each morning at 07:00, vehicles on the lot longer than the aged-stock threshold (60 days, from
    typed config) are listed in one notification to each sales manager;
  - each night at 21:00, a digest mail of the day's sales, leads and new stock goes to the managers.
- Each schedule queues a job; the job does the work. A job that cannot reach the mail server
  answers a retry with a delay, and after five attempts it fails with the reason, and the failure
  is visible with `bin/aic` (a command that lists failed jobs and retries one). Two
  `queue:work` processes never run one job twice.
- Steps in `config/native-site.json` reach every new route, a refused case included; existing steps
  stay.
- Anything AIC lacks for this is AIC work: ask the operator with `bin/aic workflow:ask`, never work
  around it in the app.

Done looks like:

- `composer run gate` passes with no deferral.
- Browser looks, kept in `tests/browser/`: a held car leaves the public list and comes back after
  the release job runs for a hold whose time has passed; the aged-stock notification lists the right
  vehicles; the digest mail's figures match the database (read through Mailpit's API). They pass
  against DDEV and in gate:23 on the compiled site.

When this run completes, stop.

## Comments

