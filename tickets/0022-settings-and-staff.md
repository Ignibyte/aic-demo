---
number: 22
title: "Settings and staff admin"
type: feature
status: open
created: 2026-10-28T12:00:00Z
updated: 2026-10-28T12:00:00Z
---

Old Lotline's Settings page was a placeholder. This run makes it real at `/app/settings`, for
admins only.

- The dealership's profile: its name (shown in the public header and on mails), address, phone,
  opening hours (used by run 18's test-drive form) and the aged-stock threshold (replacing run 20's
  config value). One row, saved by upsert; a save shows in place.
- Staff: list, add (name, email, role), change a role, deactivate and reactivate. A deactivated
  member cannot sign in, and a session they already hold is signed out on its next request. An admin
  cannot deactivate or demote the last admin.
- A new member, or one an admin resets, gets a mail with a link to set a password. The link is
  signed, works once and expires after 24 hours; a used, expired or altered link is refused with the
  app's look, and the page it lands on never redirects off the site.
- Two admins editing the same member or the profile: the second save, made on a stale copy, is
  answered 409 in place through Tessera with what changed, and nothing is overwritten.
- A non-admin sees no Settings link and is refused at every settings route.
- Steps in `config/native-site.json` reach every new route, a refused case included; existing steps
  stay.
- Anything AIC lacks for this is AIC work: ask the operator with `bin/aic workflow:ask`, never work
  around it in the app.

Done looks like:

- `composer run gate` passes with no deferral.
- Browser looks, kept in `tests/browser/`: a profile change shows in the public header; a new
  member sets a password from the mailed link (read through Mailpit) and signs in; the link refused
  a second time; a deactivated member's open session is signed out; a stale save answers 409 in
  place; the last admin cannot be demoted. They pass against DDEV and in gate:23 on the compiled
  site.

When this run completes, stop.

## Comments

