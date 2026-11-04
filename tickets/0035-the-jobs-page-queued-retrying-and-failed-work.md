---
number: 35
title: "The jobs page: queued, retrying and failed work"
type: feature
status: closed
created: 2026-11-02T18:30:00Z
updated: 2026-11-04T23:05:58Z
phase: "docs"
---

Admins see AIC's queue at `/app/jobs`: pending, running, retrying (with the next attempt's time
and the attempt count) and failed jobs (with the error), newest first, live by `data-poll`, with
"Retry now" on a failed job and "Discard". The nightly digest and the transcript mail (#32) show
up there.

AIC API it must exercise: `JobRetry` (a job that meets a transient failure, such as the mail host
refusing, and backs off), `JobFailed` after the last attempt, the queue's own tables read through
an AIC read model rather than raw SQL in the app (an AIC ticket if the framework lacks one),
`bin/aic queue:work` and `schedule:run` as documented, and a Command that replays a failed job.
A fault switch in the dev environment makes the mail transport fail so the retry path can be seen.

Every new behaviour gets a kept browser test in `tests/browser/` (also run with JavaScript off where the feature has a no-script path), and every new GET route a step in `config/native-site.json`, so gate:23 holds it on the compiled site. A defect in AIC or Tessera goes to the operator as an AIC or Tessera ticket, not a workaround here.

## Comments
