# jobs

An admin's view of AIC's queue (#35), at `/app/jobs`, linked from the sidebar for an admin alone.

## What the page shows

An intro panel says what the page is and names the console that works the queue: `bin/aic queue:work` takes the
jobs, `bin/aic schedule:run` queues the scheduled ones each minute (the nightly digest at 21:00 UTC among them),
`bin/aic queue:failed` lists what failed and `bin/aic queue:retry {id}` queues a failed job again. The notice of a
Retry now or a Discard shows there too, outside the lists. It is taken from the session only when the whole page is
built: a refresh of the lists alone builds the intro without it, so a poll from any tab never clears a notice it does
not show.

Under it one live part, anchored `jobs-live`, holds two panels:

- **In the queue**: the theme's `DataTable` `jobs-queue`, a row per job `QueueRecords::queued()` gives, newest first:
  its name, what it is about, its state as a badge (Waiting, Running or Retrying, from its `QueueState`), its
  attempts, when it is due (for a retrying job, the next attempt) and when a worker took it ("Not started" while none
  has it). An empty queue says "No job is waiting."
- **Failed**: a theme `JobCard` per job `QueueRecords::failed()` gives, newest first: its name, a Failed badge, what it
  was about, its attempts, when it failed, its number and the reason, with **Retry now** and **Discard**, each a POST
  form of its own carrying the visitor's guard fields. With none it says "No job has failed."

Times show as AIC gives them, ISO 8601 in UTC. What a job is about comes from its payload, read with AIC's
`StringMapJson`: AIC's `notify` job on the `mail` channel reads "Mail to {email}: {subject}", one on `database`
"Inbox of user {user}: {subject}", and any other job its payload's keys and values ("chat 12" for a chat transcript),
or "Nothing". The module knows no other module's jobs.

## Live

The part is the theme's `LiveParts`, which Tessera refreshes from `/app/jobs` every five seconds. As on the dashboard,
its props seal a version, a SHA-256 over what it shows (each queued job's id, state, attempts and times, each failed
job's id and time). Every answer reads the queue once for that version. A refresh of `@jobs-live` alone whose version
still holds answers 204 before the part is built; one whose props were altered answers 400. `JobsSource` lists the
queue for the panels only when the part is rendered, so a whole page reads it twice and a 204 once.

## Changes

`POST /app/jobs/failed/{id}/retry` calls `PostgresQueue::retry()`, which queues the job again with its attempts from
0, and `POST /app/jobs/failed/{id}/discard` calls `PostgresQueue::discard()`. Each answers 303 to the page with its
notice, or 404 when no failed job has the id (one retried or discarded already). A post with no token is AIC's 403.

## Access

Every route answers anyone but an admin 403 in the staff frame, before the queue is read or changed, and staff's
protected `/app` prefix sends a visitor who is not signed in to sign in.

## What it does not do

It writes no SQL: the queue is read through AIC's `QueueRecords` and changed through `PostgresQueue` alone, so no
file here names AIC's job tables. It adds no command: `bin/aic queue:retry {id}` is AIC's own.

## The retry path in the dev environment

`ddev queue-work-mail-down [max]`, a DDEV web command in `.ddev/commands/web/`, runs `bin/aic queue:work` with the
mail server out of reach (`AIC_MAIL_PORT=1`). Each mail it takes answers `JobRetry` (30 seconds, five attempts) and,
on the fifth, `JobFailed` with the transport's reason, which the page shows. It is the only fault switch: nothing in
the app reads one, and the production image never copies `.ddev/`.

## Looks

`tests/browser/jobs-page.spec.ts`, `jobs-live.spec.ts` (the poll, its 204 and its 400), `jobs-retry.spec.ts`,
`jobs-no-script.spec.ts` and `jobs-notice.spec.ts` (a poll between a Discard and the page load leaves the notice),
with `tests/browser/support/jobs.ts`.
